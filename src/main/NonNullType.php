<?php

namespace Chemisus\GraphQL;

use Exception;

class NonNullType implements Type
{
    use TypeTrait;
    use CoercerTrait;

    public function getKind(): string
    {
        return 'NON_NULL';
    }

    public function getName(): ?string
    {
        return null;
    }

    public function getFullName(): string
    {
        return sprintf("%s!", $this->getType()->getFullName());
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getField(string $name): Field
    {
        return $this->getType()->getField($name);
    }

    public function getFields()
    {
        return $this->getType()->getFields();
    }

    public function resolve(Node $node, $parent, $value)
    {
        $value = $this->getType()->resolve($node, $parent, $value);

        if ($value === null) {
            throw new Exception("%s can not be null", $node->getPath());
        }

        return $value;
    }
}