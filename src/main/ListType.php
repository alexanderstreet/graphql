<?php

namespace GraphQL;

class ListType implements Type
{
    /**
     * @var Type
     */
    private $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    public function name(): string
    {
        return $this->type->name();
    }

    /**
     * @param string $name
     * @return Field
     */
    public function field(string $name)
    {
        return $this->type->field($name);
    }

    public function resolve(Node $node, $parent, $value, Resolver $resolver = null)
    {
        $value = $resolver ? $resolver->resolve($node, $parent, $value) : $value;

        if ($value === null) {
            return null;
        }

        $array = [];

        foreach ($value as $item) {
            $array[] = $this->type->resolve($node, $parent, $item);
        }

        return $array;
    }

    public function typeOf(Node $node, $value): Type
    {
        return $this->type->typeOf($node, $value);
    }
}
