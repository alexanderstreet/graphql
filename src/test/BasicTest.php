<?php

namespace GraphQL;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var PeopleRepository
     */
    private $people;

    public function setupSchema(Schema $schema, &$graph, $people)
    {
        $schema->addField(new Field($schema, 'query', $schema->getType('Query')));

        $schema->field('query')->setFetcher(new CallbackFetcher(function (Node $node) {
            return [true];
        }));

        $schema->field('query')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
            return $value;
        }));
    }

    public function setupQuery(Schema $schema, &$graph, $people)
    {
        $query = $schema->getType('Query');
        $query->addField(new Field($query, 'greeting', $schema->getType('String')));
        $query->addField(new Field($query, 'person', $schema->getType('Person')));

        $query->field('greeting')->setResolver(new CallbackResolver(function (Node $node) {
            return sprintf("Hello, %s!", $node->arg('name', 'World'));
        }));

        $query->field('person')
            ->setFetcher(new CallbackFetcher(function (Node $node) use (&$graph, $people) {
                $name = $node->arg('name');
                $fetched = $this->people[$name];
                $graph[$name] = $fetched;
                return [$fetched];
            }))
            ->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
                return $node->items()[0];
            }));
    }

    public function setupPerson(Schema $schema, &$graph, &$people)
    {
        $person = $schema->getType('Person');
        $person->addField(new Field($person, 'name', $schema->getType('String')));
        $person->addField(new Field($person, 'father', new NonNullType($person)));
        $person->addField(new Field($person, 'mother', new NonNullType($person)));
        $person->addField(new Field($person, 'children', new ListType($person)));

        $person->field('father')
            ->setFetcher(new CallbackFetcher(function (Node $node) use (&$graph, $people) {
                $fetched = $this->people->fathersOf($node->parent()->items());

                foreach ($fetched as $person) {
                    $graph[$person->name] = $person;
                }

                return $fetched;
            }))
            ->setResolver(new CallbackResolver(function (Node $node, $parent, $value) use (&$graph) {
                return $graph[$parent->father];
            }));

        $person->field('mother')
            ->setFetcher(new CallbackFetcher(function (Node $node) use (&$graph, $people) {
                $fetched = $this->people->mothersOf($node->parent()->items());

                foreach ($fetched as $person) {
                    $graph[$person->name] = $person;
                }

                return $fetched;
            }))
            ->setResolver(new CallbackResolver(function (Node $node, $parent, $value) use (&$graph) {
                return $graph[$parent->mother];
            }));

        $person->field('children')
            ->setFetcher(new CallbackFetcher(function (Node $node) use (&$graph, $people) {
                $fetched = $this->people->childrenOf($node->parent()->items());

                foreach ($fetched as $person) {
                    $graph[$person->name] = $person;
                }

                return $fetched;
            }))
            ->setResolver(new CallbackResolver(function (Node $node, $person) use (&$graph) {
                return $this->people->childrenOf([$person]);
            }));
    }

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->people = PeopleRepository::Sample();
        $people = $this->people->toArray();

        $graph = [];

        $schema = new Schema('Schema');

        $string = new ScalarType('String');
        $query = new ObjectType('Query');
        $person = new ObjectType('Person');

        $schema->putType($query);
        $schema->putType($string);
        $schema->putType($person);

        $this->setupSchema($schema, $graph, $people);
        $this->setupQuery($schema, $graph, $people);
        $this->setupPerson($schema, $graph, $people);

        $this->schema = $schema;
    }

    public function queryXML(string $xml)
    {
        $queryBuilder = new XMLQueryReader();
        $query = $queryBuilder->read($xml);
        $executor = new BFSExecutor();
        return $executor->execute($this->schema, $query);
    }

    public function testQuery()
    {
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
        $actual = $this->queryXML($xml);
        $expect = (object) [
            'greeting' => 'Hello, Terrence!',
            'gwen' => (object) [
                'name' => 'gwen',
            ],
            'terrence' => (object) [
                'name' => 'terrence',
                'mom' => (object) [
                    'name' => 'gwen',
                    'children' => [
                        (object) [
                            'name' => 'terrence',
                        ],
                        (object) [
                            'name' => 'nick',
                        ],
                    ]
                ],
            ],
        ];

        $this->assertEquals(json_encode($expect), json_encode($actual));
    }
}
