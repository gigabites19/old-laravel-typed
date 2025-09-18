# Why

`spatie/laravel-data` is awesome because it provides typed data that plays in nicely with Laravel's validation system. Unfrotunately, it is not always possible to use this package because of real world constraints such as outdated Laravel and PHP versions.
At my job we had to upgrade a legacy project (Laravel 5.8 and PHP 7.3) to new versions of dependencies, to accomplish this I wanted to first have a strongly typed codebase together with a good set of tests covering business-critical logic. To accomplish the first part of those requirement, I went looking for a package like `spatie/laravel-data` for those versions but nothing quite fit what I wanted: something that had built-in support of Laravel's validation rules.

# How to use

Copy the 2 files anywhere in your project, adjust the namespace, read up on doc comments in `BaseDto.php` file and you're good to go.

# Examples

## Example data classes
```php
class Address extends BaseDto
{
    /**
     * @inputName address_one
     * @var string
     */
     public $addressOne;

    /**
     * @inputName building_number
     * @var ?string
     */
     public $buildingNumber;

    /**
     * @rules required_with:building_number
     * @var ?string
     */
     public $floor;

    /**
     * @var string
     */
     public $city;

    /**
     * @rules regex:/\+9955\d{8}/
     * @var string
     */
     public $phone;
}

class Customer extends BaseDto
{
    /**
     * @rules max:255
     * @inputName full_name
     * @var string
     */
    public $fullName;

    /**
     * @rules email
     * @var string
     */
    public $email;

    /**
     * @var array<string>
     */
    public $groups;

    /**
     * @var Collection<\App\DTOs\Address>
     */
    public $addresses;
}

## Creating data classes from array
```php
Customer::create([
    'full_name' => 'John Doe',
    'email' => 'johndoe@example.com',
    'groups' => [
        'regular',
     ],
    'addresses' => [
        [
            'address_one' => 'John Doe Street',
            'city' => 'Doetopia',
            'phone' => '+995500000000',
        ],
        [
            'address_one' => 'John Doe Street',
            'building_number' => '32a',
            'floor' => '3',
            'city' => 'Doetopia',
            'phone' => '+995500000000',
        ],
     ],
]);
```

# TODO

- tests
