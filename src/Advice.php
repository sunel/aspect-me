<?php 
namespace Aspect;


class Advice {

	const BEFORE = 'before';

    const AROUND = 'around';

    const AFTER = 'after';

	/**
     * The registered advice for particular join point.
     *
     * @var array
     */
    protected static $advices  = [];

    /**
     * Wrapper for before advice register.
     *
     * @param  string $id
     * @param  string $target
     * @param  string|callable  $macro
     * @param  int $sortOrder
     *
     * @return void
     */
    public static function before($id, $target, $macro, $sortOrder)
    {
        static::register($id, static::BEFORE, $target, $macro, $sortOrder);
    }

    /**
     * Wrapper for around advice register.
     *
     * @param  string $id
     * @param  string $target
     * @param  string|callable  $macro
     * @param  int $sortOrder
     *
     * @return void
     */
    public static function around($id, $target, $macro, $sortOrder)
    {
        static::register($id, static::AROUND, $target, $macro, $sortOrder);
    }


    /**
     * Wrapper for after advice register.
     *
     * @param  string $id
     * @param  string $target
     * @param  string|callable  $macro
     * @param  int $sortOrder
     *
     * @return void
     */
    public static function after($id, $target, $macro, $sortOrder)
    {
        static::register($id, static::AFTER, $target, $macro, $sortOrder);
    }

    /**
     * Register a advice for particular join point.
     *
     * @param  string $id
     * @param  string $joinPoint
     * @param  string $target
     * @param  string|callable  $macro
     * @param  int $sortOrder
     *
     * @return void
     */
    public static function register($id, $joinPoint, $target, $macro, $sortOrder)
    {
        static::$advices[$target][$joinPoint][] = $macro;
    }

    /**
     * Get the registed advice for particular join point.
     *
     * @param  string $joinPoint
     * @param  string $target
     *
     * @return array
     */
    public static function get($joinPoint, $target)
    {
    	return static::$advices[$target][$joinPoint];
    }

}