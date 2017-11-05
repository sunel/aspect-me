<?php

namespace Aspect\Command;

use Aspect\Advice;
use Aspect\Proxified;
use Psr\Container\ContainerInterface;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\MethodGenerator;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Inspect extends Command
{
	/**
     * The application container.
     *
     * @var \Psr\Container\ContainerInterface
     */
    protected $contianer;

    /**
     * The Code Handler.
     *
     * @var \Clouser
     */
    protected $codeHandler;

    /**
     * The Clean Handler.
     *
     * @var \Clouser
     */
    protected $cleanHandler;

	protected function configure()
    {
        $this->setName('aspect:inspect')
	        ->setDescription('Inspect the register aspect.')
	        ->setHelp('This command allows the joint point to be configured for the given aspect.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    	$advices = Advice::all();

    	$output->writeln('================');

    	$output->writeln([
    		'',
    		'<info>Cleaning old files !!!!</info>',
    		'',
    	]);

    	$this->cleanDirectory();

    	$codeHandler = $this->getCodeHandler();

    	foreach ($advices as $targetClass => $advice) {

    		$output->writeln("\t<info>Intercepting :: {$targetClass}</info>");

    		try {
    			$target = $this->getContainer()->get($targetClass);	
    		} catch(NotFoundExceptionInterface $e) {
    			$output->writeln("\t<comment>Oops!!! `{$targetClass}` :: has no bindings or binding not defined</comment>");
    			continue;
    		}

    		$namespace = get_class($target);
    		$className = $namespace.'\\Proxy';

    		$output->writeln("\t<info>Creating Proxy Class for {$targetClass}</info>");

    		$code = $this->generateClass($className, $this->getClassMethods($target, array_flip(array_keys($advice))), $namespace);

    		if($codeHandler($namespace, 'Proxy', $code) === true) {
    			//Advice::proxified($targetClass);
    		}
    		$output->writeln('');
    	}
    }

    protected function generateClass($className, $methods, $extends)
    {
    	$classCode  = new ClassGenerator();

    	$classCode->setName($className)
    		->setExtendedClass($extends)
    		->addUse(Advice::class)
    		->addTraits(['\\'.Proxified::class])
    		->addMethods($methods);

    	$file = FileGenerator::fromArray([
    		'classes'  => [$classCode],
    	]);

    	return $file->generate();
    }

    /**
     * Returns list of methods for given class
     *
     * @return mixed
     */
    protected function getClassMethods($source, $onlyMethods)
    {
        $methods = [MethodGenerator::fromArray($this->getDefaultConstructorDefinition($source))];

        $reflectionClass = new \ReflectionClass($source);
        $publicMethods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            if ($this->isInterceptedMethod($method) && isset($onlyMethods[$method->getName()])) {
                $methods[] = MethodGenerator::fromArray($this->getMethodInfo($method));
            }
        }
        return $methods;
    }

    /**
     * Get default constructor definition for generated class
     *
     * @return array
     */
    protected function getDefaultConstructorDefinition($source)
    {
        $reflectionClass = new \ReflectionClass($source);
        $constructor = $reflectionClass->getConstructor();
        $parameters = [];
        $body = "\$this->initlize();\n";
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $parameters[] = $this->getMethodParameterInfo($parameter);
            }
            $body .= count($parameters)
                ? "parent::__construct({$this->getParameterList($parameters)});"
                : "parent::__construct();";
        }
        return [
            'name' => '__construct',
            'parameters' => $parameters,
            'body' => $body
        ];
    }

    /**
     * Whether method is intercepted
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    protected function isInterceptedMethod(\ReflectionMethod $method)
    {
        return !($method->isConstructor() || $method->isFinal() || $method->isStatic() || $method->isDestructor()) &&
            !in_array($method->getName(), ['__sleep', '__wakeup', '__clone']);
    }

    /**
     * Retrieve method info
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    protected function getMethodInfo(\ReflectionMethod $method)
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->getMethodParameterInfo($parameter);
        }

        $methodInfo = [
            'name' => ($method->returnsReference() ? '& ' : '') . $method->getName(),
            'parameters' => $parameters,
            'body' => "\$adviceList = Advice::get(\$this->subject.'@{$method->getName()}');\n" .
                "if (empty(\$adviceList)) {\n" .
                "    return parent::{$method->getName()}({$this->getParameterList(
                $parameters
            )});\n" .
            "} else {\n" .
            "    return \$this->callAdvices('{$method->getName()}', func_get_args(), \$adviceList);\n" .
            "}",
            'returnType' => $method->getReturnType(),
            'docblock' => ['shortDescription' => '{@inheritdoc}'],
        ];

        return $methodInfo;
    }

    /**
     * @param array $parameters
     * @return string
     */
    protected function getParameterList(array $parameters)
    {
        return implode(
            ', ',
            array_map(
                function ($item) {
                    return "$" . $item['name'];
                },
                $parameters
            )
        );
    }

    /**
     * Retrieve method parameter info
     *
     * @param \ReflectionParameter $parameter
     * @return array
     */
    protected function getMethodParameterInfo(\ReflectionParameter $parameter)
    {
        $parameterInfo = [
            'name' => $parameter->getName(),
            'passedByReference' => $parameter->isPassedByReference(),
            'type' => $parameter->getType()
        ];

        if ($parameter->isArray()) {
            $parameterInfo['type'] = 'array';
        } elseif ($parameter->getClass()) {
            $parameterInfo['type'] = $this->getFullyQualifiedClassName($parameter->getClass()->getName());
        } elseif ($parameter->isCallable()) {
            $parameterInfo['type'] = 'callable';
        }

        if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
            $defaultValue = $parameter->getDefaultValue();
            if (is_string($defaultValue)) {
                $parameterInfo['defaultValue'] = $parameter->getDefaultValue();
            } elseif ($defaultValue === null) {
                $parameterInfo['defaultValue'] = $this->getNullDefaultValue();
            } else {
                $parameterInfo['defaultValue'] = $defaultValue;
            }
        }

        return $parameterInfo;
    }

    /**
     * Get fully qualified class name
     *
     * @param string $className
     * @return string
     */
    protected function getFullyQualifiedClassName($className)
    {
        $className = ltrim($className, '\\');
        return $className ? '\\' . $className : '';
    }

    /**
     * Get value generator for null default value
     *
     * @return \Zend\Code\Generator\ValueGenerator
     */
    protected function getNullDefaultValue()
    {
        $value = new \Zend\Code\Generator\ValueGenerator(null, \Zend\Code\Generator\ValueGenerator::TYPE_NULL);

        return $value;
    }

    /**
     * Get the Laravel application instance.
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the Laravel application instance.
     *
     * @param  \Psr\Container\ContainerInterface  $container
     * @return void
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

    /**
     * Get the Code Handler.
     *
     * @return \Clouser
     */
    public function getCodeHandler()
    {
        return $this->codeHandler;
    }

    /**
     * Set the Code Handler.
     *
     * @param  \Clouser  $codeHandler
     * @return void
     */
    public function setCodeHandler($codeHandler)
    {
        $this->codeHandler = $codeHandler;
    }

    /**
     * Clean the directory.
     *
     * @return void
     */
    public function cleanDirectory()
    {
        $cleanHandler = $this->cleanHandler;

        $cleanHandler();
    }

    /**
     * Set the Clean Handler.
     *
     * @param  \Clouser  $cleanHandler
     * @return void
     */
    public function setCleanHandler($cleanHandler)
    {
        $this->cleanHandler = $cleanHandler;
    }
}