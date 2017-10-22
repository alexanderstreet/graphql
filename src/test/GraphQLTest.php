<?php

namespace GraphQL;

use PHPUnit\Framework\TestCase;

class GraphQLTest extends TestCase
{
    public function testThis()
    {
        $people = [
            'terrence' => (object) [
                'name' => 'terrence',
                'mother' => 'gwen',
            ],
            'nick' => (object) [
                'name' => 'nick',
                'mother' => 'gwen',
                'father' => 'rob',
            ],
            'rob' => (object) [
                'name' => 'rob',
                'mother' => 'carol',
            ],
            'jessica' => (object) [
                'name' => 'jessica',
                'father' => 'mark',
                'mother' => 'sandra',
            ],
            'tom' => (object) [
                'name' => 'tom',
                'father' => 'carlton',
                'mother' => 'eileen',
            ],
            'gail' => (object) [
                'name' => 'gail',
                'father' => 'murial',
                'mother' => 'gilbert',
            ],
            'gwen' => (object) [
                'name' => 'gwen',
                'father' => 'tom',
                'mother' => 'gail',
            ],
            'courtney' => (object) [
                'name' => 'courtney',
                'father' => 'tom',
                'mother' => 'gail',
            ],
            'wade' => (object) [
                'name' => 'wade',
                'father' => 'tom',
                'mother' => 'gail',
            ],
        ];

        $graph = [];

        $schemaType = new ObjectType('Schema');
        $queryType = new ObjectType('Query');
        $stringType = new ScalarType('String');
        $personType = new ObjectType('Person');

        $schemaType->addField(new Field($schemaType, 'query', $queryType));
        $queryType->addField(new Field($queryType, 'greeting', $stringType));
        $queryType->addField(new Field($queryType, 'person', $personType));
        $personType->addField(new Field($personType, 'name', $stringType));
        $personType->addField(new Field($personType, 'father', new NonNullType($personType)));
        $personType->addField(new Field($personType, 'mother', new NonNullType($personType)));
        $personType->addField(new Field($personType, 'children', new ListType($personType)));

        $queryType->field('greeting')->setResolver(new CallbackResolver(function (Node $node) {
            return sprintf("Hello, %s!\n", $node->arg('name', 'World'));
        }));

        $schemaType->field('query')->setFetcher(new CallbackFetcher(function (Node $node) {
            return [true];
        }));

        $queryType->field('person')->setFetcher(new CallbackFetcher(function (Node $node) use ($people, &$graph) {
            $name = $node->arg('name');
            $fetched = array_key_exists($name, $people) ? $people[$name] : null;
            $graph[$name] = $fetched;
            return [$fetched];
        }));

        $personType->field('children')->setFetcher(new CallbackFetcher(function (Node $node) use ($people, &$graph) {
            $fetched = array_filter(array_merge([], ...array_map(function ($person) use ($people) {
                return array_values(array_filter($people, function ($child) use ($person) {
                    return array_key_exists('father', $child) && $child->father === $person->name ||
                        array_key_exists('mother', $child) && $child->mother === $person->name;
                }));
            }, $node->parent()->items())));

            foreach ($fetched as $person) {
                $graph[$person->name] = $person;
            }

            return $fetched;
        }));

        $personType->field('father')->setFetcher(new CallbackFetcher(function (Node $node) use ($people, &$graph) {
            $fetched = array_values(array_filter(array_map(function ($person) use ($people) {
                return array_key_exists($person->father, $people) ? $people[$person->father] : null;
            }, $node->parent()->items())));

            foreach ($fetched as $person) {
                $graph[$person->name] = $person;
            }

            return $fetched;
        }));

        $personType->field('mother')->setFetcher(new CallbackFetcher(function (Node $node) use ($people, &$graph) {
            $fetched = array_values(array_filter(array_map(function ($person) use ($people) {
                return array_key_exists($person->mother, $people) ? $people[$person->mother] : null;
            }, $node->parent()->items())));

            foreach ($fetched as $person) {
                $graph[$person->name] = $person;
            }

            return $fetched;
        }));


        $schemaType->field('query')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
            return $value;
        }));

        $queryType->field('person')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
            return $node->items()[0];
        }));

        $personType->field('children')->setResolver(new CallbackResolver(function (Node $node, $person) use (&$graph) {
            return array_values(array_filter($graph, function ($child) use ($person) {
                return array_key_exists('father', $child) && $child->father === $person->name ||
                    array_key_exists('mother', $child) && $child->mother === $person->name;
            }));
        }));

        $personType->field('father')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) use (&$graph) {
            return $graph[$parent->father];
        }));

        $personType->field('mother')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) use (&$graph) {
            return $graph[$parent->mother];
        }));

        $xml = <<< _XML
<query xmlns:gql="graphql">
    <greeting name="Terrence"/>
    <person gql:alias="gwen" name="gwen">
        <name/>
    </person>
    <person gql:alias="terrence" name="terrence">
        <name/>
        <mother gql:alias="mom">
            <name/>
            <children>
                <name/>
            </children>
        </mother>
    </person>
</query>
_XML;

        $queryBuilder = new XMLQueryReader();
        $query = $queryBuilder->read($xml);
        $executor = new BFSExecutor();
        $value = $executor->execute($schemaType, $query);

        error_log(json_encode($value, JSON_PRETTY_PRINT));
        error_log(json_encode($graph, JSON_PRETTY_PRINT));

        $this->assertTrue(true);
    }
}