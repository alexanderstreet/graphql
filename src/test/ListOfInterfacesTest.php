<?php

namespace GraphQL;

use PHPUnit\Framework\TestCase;

class ListOfInterfacesTest extends TestCase
{
    /**
     * @var Schema
     */
    private $schema;

    public function setupSchema(Schema $schema, &$graph, $people, $dogs, $cats)
    {
        $schema->addField(new Field($schema, 'query', $schema->getType('Query')));

        $schema->field('query')->setFetcher(new CallbackFetcher(function (Node $node) {
            return [true];
        }));

        $schema->field('query')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
            return $value;
        }));
    }

    public function setupQuery(Schema $schema, &$graph, $people, $dogs, $cats)
    {
        $query = $schema->getType('Query');
        $query->addField(new Field($query, 'people', new ListType($schema->getType('Person'))));

        $query->field('people')->setFetcher(new CallbackFetcher(function (Node $node) use (&$graph, $people) {
            $names = explode(',', $node->arg('names'));
            $fetched = array_map(function ($name) use (&$people) {
                return array_key_exists($name, $people) ? $people[$name] : null;
            }, $names);

            foreach ($fetched as $item) {
                $graph[$item->name] = $item;
            }

            return $fetched;
        }));

        $query->field('people')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) {
            return $node->items();
        }));
    }

    public function setupPerson(Schema $schema, &$graph, &$people, &$dogs, &$cats)
    {
        $person = $schema->getType('Person');
        $person->addField(new Field($person, 'name', $schema->getType('String')));
        $person->addField(new Field($person, 'pets', new ListType($schema->getType('Animal'))));

        $person->field('pets')->setFetcher(new CallbackFetcher(function (Node $node) use ($people, &$graph, $cats, $dogs) {
            $fetched = array_merge(...array_map(function ($person) use ($cats, $dogs) {
                return array_values(array_merge(
                    array_filter($cats, function ($cat) use ($person) {
                        return $cat->owner === $person->name;
                    }),
                    array_filter($dogs, function ($dog) use ($person) {
                        return $dog->owner === $person->name;
                    })
                ));
            }, $node->parent()->items()));

            foreach ($fetched as $animal) {
                $graph[$animal->name] = $animal;
            }

            return $fetched;
        }));

        $person->field('pets')->setResolver(new CallbackResolver(function (Node $node, $parent, $value) use (&$graph) {
            return array_values(array_filter($graph, function ($animal) use ($parent) {
                return $animal->owner === $parent->name;
            }));
        }));
    }

    public function setupAnimal(Schema $schema, &$graph, &$people, &$dogs, &$cats)
    {
        $animal = $schema->getType('Animal');
        $animal->addField(new Field($animal, 'name', $schema->getType('String')));

        $animal->typer = new CallbackTyper(function (Node $node, $value) {
            return $node->schema()->getType($value->type);
        });
    }

    public function setupDog(Schema $schema, &$graph, &$people, &$dogs, &$cats)
    {
        $dog = $schema->getType('Dog');
        $dog->addField(new Field($dog, 'name', $schema->getType('String')));
        $dog->addField(new Field($dog, 'guard', $schema->getType('Boolean')));
    }

    public function setupCat(Schema $schema, &$graph, &$people, &$dogs, &$cats)
    {
        $cat = $schema->getType('Cat');
        $cat->addField(new Field($cat, 'name', $schema->getType('String')));
        $cat->addField(new Field($cat, 'lives', $schema->getType('Integer')));
    }

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $people = [
            'terrence' => (object) [
                'name' => 'terrence',
            ],
            'martin' => (object) [
                'name' => 'martin',
            ],
        ];

        $dogs = [
            'gunner' => (object) [
                'type' => 'Dog',
                'name' => 'gunner',
                'owner' => 'terrence',
                'guard' => true,
            ],
        ];

        $cats = [
            'tubs' => (object) [
                'type' => 'Cat',
                'name' => 'tubs',
                'owner' => 'martin',
                'lives' => 9,
            ],
        ];

        $graph = [];

        $schema = new Schema('Schema');

        $schema->putType(new ScalarType('String'));
        $schema->putType(new ScalarType('Integer'));
        $schema->putType(new ScalarType('Boolean'));
        $schema->putType(new ObjectType('Query'));
        $schema->putType(new ObjectType('Person'));
        $schema->putType(new InterfaceType('Animal'));
        $schema->putType(new ObjectType('Dog'));
        $schema->putType(new ObjectType('Cat'));

        $this->setupSchema($schema, $graph, $people, $dogs, $cats);
        $this->setupQuery($schema, $graph, $people, $dogs, $cats);
        $this->setupPerson($schema, $graph, $people, $dogs, $cats);
        $this->setupAnimal($schema, $graph, $people, $dogs, $cats);
        $this->setupDog($schema, $graph, $people, $dogs, $cats);
        $this->setupCat($schema, $graph, $people, $dogs, $cats);

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
    <people names="terrence,martin">
        <name/>
        <pets>
            <name/>
            <guard gql:on="Dog"/>
            <lives gql:on="Cat"/>
        </pets>
    </people>
</query>
_XML;
        $actual = $this->queryXML($xml);
        $expect = (object) [
            'people' => [
                (object) [
                    'name' => 'terrence',
                    'pets' => [
                        (object) [
                            'name' => 'gunner',
                            'guard' => true,
                        ]
                    ],
                ],
                (object) [
                    'name' => 'martin',
                    'pets' => [
                        (object) [
                            'name' => 'tubs',
                            'lives' => 9,
                        ]
                    ],
                ],
            ],
        ];

        $this->assertEquals(json_encode($expect), json_encode($actual));
    }
}