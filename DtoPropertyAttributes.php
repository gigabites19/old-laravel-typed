<?php

namespace App\DTOs;

class DtoPropertyAttributes
{
    /** @var string */
    public $name;

    /** @var string */
    public $inputName;

    /** @var ?string */
    public $rules;

    /** @var string */
    public $type;

    public function __construct(
        string $name,
        string $inputName,
        ?string $rules,
        string $type
    )
    {
        $this->name = $name;
        $this->inputName = $inputName;
        $this->rules = $rules;
        $this->type = $type;
    }

    public static function create(array $data): self
    {
        return new static(
            $data['name'],
            $data['inputName'],
            $data['rules'],
            $data['type'],
        );
    }
}
