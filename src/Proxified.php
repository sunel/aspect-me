<?php

namespace Aspect;


trait Proxified
{
	/**
     * The parent class.
     *
     * @var string
     */
	protected $subject;

	/**
     * Initialize the Interceptor
     *
     * @return void
     */
    public function initlize()
    {
        $this->subject = get_parent_class($this);
    }

    /**
     * Calls parent class method
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function callParent($method, array $arguments)
    {
        return parent::$method(...array_values($arguments));
    }

    /**
     * Calls Advices.
     *
     * @param string $method
     * @param array $arguments
     * @param array $adviceList
     * @return mixed|null
     */
    protected function callAdvices($method, array $arguments, array $adviceList)
    {
        $subject = $this;
       
    	$capMethod = ucfirst($method);

    	$container = Advice::getObjectResolver();

    	if (isset($adviceList[Advice::BEFORE])) {

    		$beforeList = $this->sortBy(function($item) {
	        	return $item['order'];
	        }, $adviceList[Advice::BEFORE]);

            // Call 'before' listeners
            foreach ($beforeList as $code) {
            	$callable = $code['weaver'];
            	if($this->useAsCallable($callable)) {
            		$beforeResult = $callable($subject, ...array_values($arguments));
            	} else {
            		$pluginInstance = $container($callable);
            		if($pluginInstance !== false) {
		                $pluginMethod = 'before' . $capMethod;
		                $beforeResult = $pluginInstance->$pluginMethod($subject, ...array_values($arguments));
		            }
	            }

                if ($beforeResult !== null) {
                    $arguments = (array)$beforeResult;
                }
            }
        }

       if (isset($adviceList[Advice::AROUND])) {

       		// Call 'around' listener
       		$aroundList = $this->sortBy(function($item) {
	        	return $item['order'];
	        }, $adviceList[Advice::AROUND]);	        

	        $next = function (...$arguments) use ($subject, $method, $capMethod, &$aroundList, &$next) {
	        	list(, $code) = each($aroundList);
	        	if(!is_null($code)) {
	        	 	$callable = $code['weaver'];
		            if($this->useAsCallable($callable)) {
		        		$result = $callable($subject, $next, ...array_values($arguments));
		        	} else { 
		        		$pluginInstance = $container($callable);
		        		if($pluginInstance !== false) {                  
			                $pluginMethod = 'around' . $capMethod;
			                $result = $pluginInstance->$pluginMethod($subject, ...array_values($arguments));
			            } else {
			            	$result = $next(...array_values($arguments));
			            }
		            }
		        } else {
		        	$result = $subject->callParent($method, $arguments);
		        }

		        return $result;
	        };           	     

            $result = $next(...array_values($arguments));
        } else {
            // Call original method
            $result = $subject->callParent($method, $arguments);
        }

        if (isset($adviceList[Advice::AFTER])) {

    		$afterList = $this->sortBy(function($item) {
	        	return $item['order'];
	        }, $adviceList[Advice::AFTER]);

            // Call 'after' listeners
            foreach ($afterList as $code) {
            	$callable = $code['weaver'];
            	if($this->useAsCallable($callable)) {
            		$result = $callable($subject, $result, ...array_values($arguments));
            	} else {
            		$pluginInstance = $container($callable); 
            		if($pluginInstance !== false) {              
		                $pluginMethod = 'after' . $capMethod;
		                $result = $pluginInstance->$pluginMethod($subject, $result, ...array_values($arguments));
		            }
	            }
            }
        }

        return $result;
        
    }

    /**
     * Sort the array using the given callback.
     *
     * @param  callable  $callback
     * @param  array  $items
     * @return array
     */
    public function sortBy($callback, $items)
    {
        $results = [];

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        arsort($results, SORT_NUMERIC);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $items[$key];
        }

        return $results;
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function useAsCallable($value)
    {
        return ! is_string($value) && is_callable($value);
    }
}