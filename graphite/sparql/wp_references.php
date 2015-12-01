#!/usr/bin/php
<?php

/**
 * @author Addshore
 *
 * Counts the number of wikidata references that include the snaks:
 *     stated in -> Wikipedia language X
 *  OR
 *     imported from -> Wikipedia language X
 */

require_once( __DIR__ . '/../../src/WikimediaCurl.php' );

$metrics = new WikidataWikipediaReferences();
$metrics->execute();

class WikidataWikipediaReferences{

	public function execute() {
		$itemIds = $this->getWikipediaItemIds();
		$count = 0;
		foreach( array_chunk( $itemIds, 5, true ) as $chunkedItemItems ) {
			$count += $this->getReferenceCount( $chunkedItemItems );
		}
		$this->sendMetric( 'daily.wikidata.datamodel.wikipedia_references', $count );
	}

	private function getReferenceCount( $itemIds ) {
		$query = "PREFIX prov: <http://www.w3.org/ns/prov#>";
		$query .= "PREFIX wd: <http://www.wikidata.org/entity/>";
		$query .= "PREFIX wdt: <http://www.wikidata.org/prop/direct/>";
		// TODO FIXME this does not currently account for statements that may be imported from 2 wikis
		// Note: They will thus be counted twice if their wikis are in different chunks
		$query .= "SELECT (count(distinct(?s)) AS ?scount) WHERE {";
		$subQueries = array();
		foreach( $itemIds as $itemId ) {
			// Imported from
			$subQueries[] = "{ ?wdref <http://www.wikidata.org/prop/reference/P143> wd:$itemId }";
			// Stated in
			$subQueries[] = "{ ?wdref <http://www.wikidata.org/prop/reference/P248> wd:$itemId }";
		}
		$query .= implode( " UNION ", $subQueries );
		$query .= "?s prov:wasDerivedFrom ?wdref";
		$query .= "}";

		$result = $this->doSparqlQuery( $query );
		return $result['results']['bindings'][0]['scount']['value'];
	}

	/**
	 * @return array keys are dbnames, values are string item itds
	 */
	private function getWikipediaItemIds() {
		$query = "PREFIX wd: <http://www.wikidata.org/entity/>";
		$query .= "PREFIX wdt: <http://www.wikidata.org/prop/direct/>";
		$query .= "PREFIX wikibase: <http://wikiba.se/ontology#>";
		$query .= "SELECT ?item ?dbname WHERE {";
		// https://www.wikidata.org/wiki/Q10876391 is Wikipedia language edition
		$query .= "?item  wdt:P31 wd:Q10876391 . ";
		$query .= "?item  wdt:P1800 ?dbname";
		$query .= "}";

		$result = $this->doSparqlQuery( $query );

		$itemIds = array();
		foreach( $result['results']['bindings'] as $binding ) {
			$dbname = $binding['dbname']['value'];
			$itemId = str_replace( 'http://www.wikidata.org/entity/', '', $binding['item']['value'] );
			$itemIds[$dbname] = $itemId;
		}
		return $itemIds;
	}

	/**
	 * @param string $query
	 *
	 * @return array
	 */
	private function doSparqlQuery ( $query ) {
		$response = WikimediaCurl::curlGet( "https://query.wikidata.org/bigdata/namespace/wdq/sparql?format=json&query=" . urlencode( $query ) );

		if( $response === false ) {
			throw new RuntimeException( "The SPARQL request failed!" );
		}

		return json_decode( $response[1], true );
	}

	private function sendMetric( $name, $value ) {
		exec( "echo \"$name $value `date +%s`\" | nc -q0 graphite.eqiad.wmnet 2003" );
	}

}
