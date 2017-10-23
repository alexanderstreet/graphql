<?php

namespace GraphQL;

use GraphQL\Types\FieldedType;
use GraphQL\Types\ObjectType;
use GraphQL\Types\Type;

class Field
{
    /**
     * @var Fetcher
     */
    public $fetcher;

    /**
     * @var Resolver
     */
    public $resolver;

    /**
     * @var ObjectType
     */
    private $ownerType;

    /**mixed
     * @var string
     */
    private $name;

    /**
     * @var Type
     */
    private $returnType;

    /**
     * Field constructor.
     * @param FieldedType $ownerType
     * @param string $name
     * @param Type $returnType
     */
    public function __construct(FieldedType $ownerType, string $name, Type $returnType)
    {
        $this->ownerType = $ownerType;
        $this->name = $name;
        $this->returnType = $returnType;
    }

    public function hasFetcher()
    {
        return $this->fetcher !== null;
    }

    public function setFetcher(Fetcher $fetcher): self
    {
        $this->fetcher = $fetcher;
        return $this;
    }

    public function setResolver(Resolver $resolver): self
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function types(Node $node, $items)
    {

    }

    public function fetch(Node $node)
    {
        return $this->fetcher ? $this->fetcher->fetch($node) : [];
    }

    public function resolve(Node $node, $parent, $value)
    {
        return $this->returnType->resolve($node, $parent, $value, $this->resolver);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ownerType(): ObjectType
    {
        return $this->ownerType;
    }

    public function returnType(): Type
    {
        return $this->returnType;
    }
}
