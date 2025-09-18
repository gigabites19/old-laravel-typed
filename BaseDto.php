<?php

namespace App\DTOs;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * This class aims to provide functionality similar to `spatie/laravel-data` before we bump up
 * PHP and Laravel versions enough that we can use the actual package.
 *
 * Using this class you can:
 * - Define data classes and their properties
 * - Define rules and validate properties using Laravel's validation rules upon instantiation
 * - Create data class instances using unknown `array $data` and get validation errors in case of invalid structure
 *
 * The benefit of using this is that instead of blindly accessing array properties, we have typed data
 * with auto-completion and validation.
 *
 * If you can create an instance of the data class, then you can rest easy
 * knowing the data you're working with is valid.
 *
 * Mapping input array's key to an internal property when they differ should be done using the `@inputName` tag.
 * `company` will be mapped because the key in the array and property name are the same.
 *
 * ```php
 * $data = [
 *     'first_name' => 'John',
 *     'company' => 'acme',
 * ];
 *
 * Class Person extends BaseDto
 * {
 *     /**
 *      * @inputName first_name
 *      * @var string
 *      *\/
 *      public $firstName;
 *
 *      /**
 *       * @inputName company
 *       * @var string
 *       *\/
 *      public $company;
 * }
 * ```
 *
 * If you want to create a property which has many items (like an array or a collection), a couple of rules apply:
 * 1. Only primitive types (int, string, bool, etc.) can be used with `array`. E.g.: `array<string>`
 * 2. Only FQCNs of DTOs can be used with `Collection` and they should be a subclass of `BaseDto`. E.g.: `Collection<\App\DTOs\CustomerAddress>`
 *
 * Generic collection types do not yet support key types. E.g.: array<int, string>, Collection<int, int>
 *
 * # Validation
 * Some validation rules are applied conditionally. See the `getImplicitRulesForType` method for more info.
 */
abstract class BaseDto
{
    /**
     * Extract the value of a property's comment's tag.
     *
     * @example
     * ```php
     * class AddressDto extends BaseDto
     * {
     *     /**
     *      * @rules max:255
     *      * @var string
     *      *\/
     *      public $firstName;
     * }
     *
     * AddressDto::getPropertyCommentTagValue($property, 'rules'); // required|max:255
     * AddressDto::getPropertyCommentTagValue($property, 'rulez'); // null
     * ```
     */
    protected static function getPropertyCommentTagValue(ReflectionProperty $property, string $tagName): ?string
    {
        $docComment = $property->getDocComment();

        $matches = [];
        preg_match("/\*\s@{$tagName}\s(\??.*)\n/", $docComment, $matches);

        return data_get($matches, 1);
    }

    /**
     * Get implicit rules for a type.
     *
     * Implicit rules are Laravel validation rules that are automatically applied to a property
     * based on its type signature. E.g.:
     * - `?string` implies: `nullable|string`.
     * - `?int` implies: `nullable|numeric`
     * - `string` implies: `required|string`
     *
     * Rules are the following:
     * `required` when a property cannot be `null`
     * `nullable` when a property can be `null`
     * `numeric` when a property type is `int`
     * `string` when a property type is `string`
     * `boolean` when a property type is `bool`
     * `numeric` when a property type is `float`
     * `array` when a property type is `array`
     */
    protected static function getImplicitRulesForType(string $type): string
    {
        /**
         * Type signatures that `$type` should contain for validation rule
         * <key> to be applied.
         *
         * @var array<string, string|Closure> $rulesAndSignatureBits
         */
        $rulesAndSignatureBits = [
            'required' => function (string $type) {
                return preg_match('/\?.*|null\||\|null/', $type) === 0;
            },
            'nullable' => '/\?.*|null\||\|null/',
            'numeric' => '/(?<!<)int|integer|float|double(?!>)/', // only match non-generic
            'string' => '/(?<!<)string(?!>)/', // only match non-generic
            'boolean' => '/(?<!<)bool|boolean(?!>)/', // only match non-generic
            'array' => '/array/',
        ];

        $rules = [];

        foreach ($rulesAndSignatureBits as $rule => $signatureRegexOrClosure) {
            if (
                (is_callable($signatureRegexOrClosure) && $signatureRegexOrClosure($type))
                || (is_string($signatureRegexOrClosure) && preg_match($signatureRegexOrClosure, $type))
            ) {
                $rules[] = $rule;
            }
        }

        return '|' . implode('|', $rules);
    }

    /**
     * Get the class supplied to the generic class as an argument
     *
     * @example
     * ```php
     * BaseDto::getGenericArgument('Collection<\App\DTOs\CustomerAddress>'); // \App\DTOs\CustomerAddress
     * ```
     */
    protected static function getGenericArgument(string $genericTag): ?string
    {
        $matches = [];
        preg_match('/\w+<(.*)>/', $genericTag, $matches);

        return data_get($matches, 1);
    }

    /**
     * Determine if the type of property is a generic collection.
     */
    protected static function propertyTypeIsGenericCollection(string $type): ?string
    {
        return preg_match('/Collection<.*>/', $type) === 1;
    }

    public static function create(array $data): self
    {
        $reflector = new ReflectionClass(new static);
        $properties = collect($reflector->getProperties());
        /** @var Collection<int, DtoPropertyAttributes> $propertyAttributes */
        $propertyAttributes = $properties->map(function ($property): DtoPropertyAttributes {
            $inputName = static::getPropertyCommentTagValue($property, 'inputName') ?? $property->getName();
            $rules = static::getPropertyCommentTagValue($property, 'rules');
            $type = static::getPropertyCommentTagValue($property, 'var');

            return DtoPropertyAttributes::create([
                    'name' => $property->getName(),
                    'inputName' => $inputName,
                    'rules' => $rules . static::getImplicitRulesForType($type),
                    'type' => $type,
                ]);
        });

        $rules = $propertyAttributes->mapWithKeys(function (DtoPropertyAttributes $attributes) {
            $rules = [$attributes->inputName => $attributes->rules];

            if (preg_match('/array<.*>/', $attributes->type)) {
                $rules["{$attributes->inputName}.*"] = static::getGenericArgument($attributes->type);
            }

            return $rules;
        })->toArray();

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            $class = static::class;
            $error = Arr::first($validator->errors()->messages())[0];

            throw new RuntimeException("Validation for {$class} failed. Error: $error");
        }

        $instance = new static;
        $data = $validator->validated();

        foreach ($data as $property => $value) {
            /** @var DtoPropertyAttributes $attributes */
            $attributes = $propertyAttributes->firstWhere('inputName', $property);

            if (class_exists($attributes->type) && is_subclass_of($attributes->type, self::class)) {
                $value = $attributes->type::create($value);
            } else if (static::propertyTypeIsGenericCollection($attributes->type)) {
                $collectionItemType = static::getGenericArgument($attributes->type);

                if (
                    !class_exists($collectionItemType)
                    || !is_subclass_of($collectionItemType, self::class)
                ) {
                    throw new RuntimeException("Collection item type should be a subclass of " . self::class);
                }

                // A collection of DTOs. e.g.: Collect<\App\DTOs\CustomerAddress>
                $value = collect($value)->map(function ($item) use ($collectionItemType) {
                    return $collectionItemType::create($item);
                });
            }

            $instance->{$attributes->name} = $value;
        }

        return $instance;
    }
}
