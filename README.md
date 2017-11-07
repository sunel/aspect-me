# Aspect Me


Intercept and change the behavior of a public method of a class during before, after, or around function call.

In aspect-oriented programming (AOP) is a programming paradigm that aims to increase modularity by allowing the separation of cross-cutting concerns. It does so by adding additional behavior to existing code (an advice) without modifying the code itself, instead separately specifying which code is modified via a "pointcut" specification. This allows behaviors that are not central to the business logic to be added to a program without cluttering the code, core to the functionality.


### Why APO?

Consider you want change the output of a function in class or do something when the method is called. Usually we rewrite the class and extend the method definitions in order to overwrite it.

Class rewrites are popular because they allow a very specific redefinition of system functionality.

There are, however, some problems with class rewrites. You can only rewrite a class only once. Once one module claims a method, other modules are out of luck.

This package allows third party developers to create modules or packages that could listen for and change the behavior of the class method, so they do not conflict with one another.

### Limitations

- This will work only on the system that uses dependency injection containers [PSR-11](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-11-container.md).
- Can be used only on public methods.
- Class must be resolved through DI containers.

### Installation

Via [composer](http://getcomposer.org):

```bash
$ composer require sunel/aspect-me
```

For [#laravel](#laravel)


### How?

Let's consider we have class called ```Post```

```php

interface PostInterface {

    public function setTitle($title);

    public function getTitle();

}


class Post implements PostInterface {
	
    public function setTitle($title)
    {
    	$this->title = $title;
    }

    public function getTitle()
    {
    	return  $this->title;
    }
}


$container->bind(\PostInterface::class, \Post::class);

```


We need to change the first character to uppercase, in this case we can make the changes after ```getTitle``` is been called. So we first let the system know about it

```php
\Aspect\Advice::after('change_post_title_to_uppercase', \PostInterface::class.'@getTitle', function(\PostInterface $subject,  $result, ...$args) {
    return ucfirst($result);
}, 100);
```

Now we need to prepare the system to update based on the advice.

> Details about the command will be given below

```shell
$ aspect:inspect
```


Ok, So now if we called the  ```getTitle``` from the  ```Post``` class through the DI the result will contain  the first character to be uppercased.

```php
$post = $container->get(\PostInterface::class);

$post->setTitle('aspect');

$post->getTitle(); ## Aspect

```


### AOP Concepts

```\Aspect\Advice``` : registers the action taken by an aspect at a particular join point.

Join point : A point during the execution of a program, such as the execution of a method. Include "before", "around" and "after" advice.

#### Before : 

Even with systems like automatic constructor dependency injection, there’s no way to change this argument before the ```setTitle``` method gets called.

This sort of problem is what the before plugin methods can solve. If the method does not change the argument for the observed method, it should return null.

```php
\Aspect\Advice::before('trim_post_title', \PostInterface::class.'@setTitle', function(\PostInterface $subject, ...$args) {
	
	# We trim the given title
    return [trim($args[0])];

}, 100);
```

#### After : 

This is sort of like an observer for class method. The system will call after the ```getTitle``` method is called. You can use these methods to change the result of an observed method by modifying the original result and returning it at the end of the method.

```php
\Aspect\Advice::after('change_post_title_to_uppercase', \PostInterface::class.'@getTitle', function(\PostInterface $subject,  $result, ...$args) {
    return ucfirst($result);
}, 100);
```

After methods have access to all the arguments of their observed methods. When the observed method completes, System passes the result and arguments to the next after method that follows. If observed method does not return a result, then it passes null to the next after method.


#### Around : 

This runs the code in around methods before and after their observed methods. The around methods fire during, or as a replacement to the original method.

```php
\Aspect\Advice::around('change_post_title', \PostInterface::class.'@getTitle', function(\PostInterface $subject,  callable $proceed, ...$args) {

	echo 'Calling'.' -- before',"\n";
    $result = $proceed(...$args)
    echo 'Calling' . ' -- after',"\n";

    return $result;

}, 100);
```

The around plugin methods give you the ability, in a single place, to have code run both before the original method, and after the original method. How it does this is through the second parameter.

This second parameter ```$proceed``` is an anonymous function ```Closure```. If you are using an around plugin method, you call/invoke this closure when you want the system to call the original method. You are responsible for forwarding the arguments from the plugin to the proceed callable.

It also means you can cancel the call to the original method, and substitute your own return value.


**Note:** All the Advices can be prioritized by provding 4th argument between 10-100, Higher the value higher the priority.


### Integration

#### Laravel

You'll need to register the service provider, in your `config/app.php`:

```php
'providers' => [
	...
	Aspect\Provider\LaravelServiceProvider::class
]
```

Add this to composer.json

```json
{
    "autoload": {
        "classmap": [
            "resources/generated/"
        ]
    }
}
```

Run this to let composer know about the new autoload

```bash
$ composer dump-autoload
```

For the artisan command 

```bash
$ php artisan aspect:inspect
```




