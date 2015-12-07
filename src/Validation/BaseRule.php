<?php
/**
 *
 */



namespace Golem\Validation;

use

	  Golem\Golem

	, Golem\iFace\ValidationRule

	, Golem\Traits\Seal
	, Golem\Traits\HasOptions
	, Golem\Traits\HasLog

	, Golem\Data\String

	, Golem\Util
;

/**
 * The mother of all rules.
 *
 */
abstract
class      BaseRule
implements ValidationRule
{

use HasOptions, Seal, HasLog;

protected $golem;

/**
 * Used for input type checking.
 */
protected $inputType;


abstract protected function ensureType( $value );



public
function __construct( Golem $golem, array $options = [] )
{
	$this->golem = $golem;

	$this->setupOptions( $golem->options( 'Validation', 'BaseRule' ), $options );
	$this->setupLog();
}



public
function copy()
{
	$c         = clone $this;
	$c->sealed = false      ;

	return $c;
}



protected
function validateOptions()
{
	$o = &$this->options;

	isset( $o[ 'in'        ] )  &&  $o[ 'in'        ] = $this->validateOptionEncoding ( $o[ 'in'        ] );
	isset( $o[ 'type'      ] )  &&  $o[ 'type'      ] = $this->validateOptionType     ( $o[ 'type'      ] );
	isset( $o[ 'allowNull' ] )  &&  $o[ 'allowNull' ] = $this->validateOptionAllowNull( $o[ 'allowNull' ] );
}



protected
function validateOptionIn( $o )
{
	if( ! is_array( $o ) )

		$this->log->invalidArgumentException
		(
			  "Option 'in' should be an array. Got: "
			. var_export( $o, /* return = */ true )
		)
	;


	foreach( $o as $key => $allowed )

		$o[ $key ] = $this->ensureType( $allowed )
	;


	return $o;
}



protected
function validateOptionAllowNull( $o )
{
	if( is_bool( $o ) )

		return $o;


	$this->log->invalidArgumentException
	(
		  "Option 'allowNull' should be a boolean. Got: "
		. var_export( $o, /* return = */ true )
	);
}



protected
function validateOptionType( $o )
{
	if( is_string( $o ) || $o instanceof String )

		return $o;


	$this->log->invalidArgumentException
	(
		  "Option 'type' should be an a string or a Golem\Data\String. Got: "
		. var_export( $o, /* return = */ true )
	);
}



public
function sanitize( $input, $context )
{
	$this->inputType = Util::getType( $input );

	$input = $this->ensureType  ( $input           );

	$input = $this->sanitizeType( $input, $context );
	$input = $this->sanitizeIn  ( $input, $context );

	return $input;
}



public
function validate( $input, $context )
{
	$this->inputType = Util::getType( $input );

	$input = $this->ensureType  ( $input           );

	$input = $this->validateType( $input, $context );
	$input = $this->validateIn  ( $input, $context );

	return $input;
}



public
function allowNull( $value = null )
{
	// getter
	//
	if( $value === null )

		return $this->options[ 'allowNull' ];


	// setter
	//
	$this->checkSeal();

	$this->options[ 'allowNull' ] = $this->validateOptionAllowNull( $value );


	return $this;
}



public
function in()
{
	$args = func_get_args();


	// getter
	//
	if( ! $args )

		return $this->options[ 'in' ];


	// setter
	// if list is passed as array
	//
	if( is_array( $args[ 0 ] ) )

		$args = $args[ 0 ];


	$this->checkSeal();

	$this->options[ 'in' ] = $this->validateOptionIn( $args );

	return $this;
}



public
function sanitizeIn( $input, $context )
{
	if( $this->isValidIn( $input ) )

		return $input;


	if( isset( $this->options[ 'defaultValue' ] ) )

		return $this->validate( $this->options[ 'defaultValue' ], $context );


	$this->log->validationException
	(
		  "$context: No default value set and input value [$input] not found in list: "
		. var_export( $this->options( 'in' ), /* return = */ true )
	);
}



public
function validateIn( $input, $context )
{
	if( $this->isValidIn( $input ) )

		return $input;


	$this->log->validationException
	(
		  "$context: Input value [$input] not found in list: "
		. var_export( $this->options( 'in' ), /* return = */ true )
	);
}



public
function isValidIn( $input )
{
	if( ! isset( $this->options[ 'in' ] ) )

		return true;



	foreach( $this->options( 'in' ) as $allowed )

		if( $this->areEqual( $input, $allowed ) )

			return true;


	return false;
}



protected
function areEqual( $a, $b )
{
	return $a === $b;
}



public
function type( $type = null )
{
	// getter
	//
	if( $type === null )

		return $this->options[ 'type' ];


	// setter
	//
	$this->checkSeal();

	$this->options[ 'type' ] = $this->validateOptionType( $type );

	return $this;
}



public
function sanitizeType( $input, $context )
{
	if( $this->isValidType( $this->inputType )  ||  $this->isValidType( Util::getType( $input ) ) )

		return $input;


	if( isset( $this->options[ 'defaultValue' ] ) )

		return $this->validate( $this->options[ 'defaultValue' ], $context );


	$this->log->validationException
	(
		  "$context: No default value set and input value [$input] is not of type: {$this->options['type']}, "
		. "got a: $this->inputType. for input: " . var_export( $input, /* return = */ true )
	);
}



public
function validateType( $input, $context )
{
	// Only check the type when the value came in, not the current one which might have been cast by ensureType.
	//
	if( $this->isValidType( $this->inputType ) )

		return $input;


	$this->log->validationException
	(
		  "$context: Input value [$input] is not of type: {$this->options['type']}, got a: $this->inputType. "
		. "for input: " . var_export( $input, /* return = */ true )
	);
}



public
function isValidType( $type )
{
	if
	(
		   ! isset( $this->options[ 'type' ] )
		|| $this->inputType === $this->options[ 'type' ]
 	)

		return true;


	return false;
}



protected
function annotateContext( $context )
{
	// only do it once
	//
	if( preg_match( '/called from: /', $context ) === 1 )

		return $context;


	return

		  $context
		. 'called from: '
		. debug_backtrace()[ 2 ][ 'class' ]
		. debug_backtrace()[ 2 ][ 'type'  ]
		. debug_backtrace()[ 2 ][ 'function' ] . "(); \n"
	;
}



protected
function validNull( $input )
{
	if( isset( $this->options[ 'allowNull' ] )  &&  $this->options[ 'allowNull' ] === true  &&  $input === null )

		return true;


	return false;
}



}
