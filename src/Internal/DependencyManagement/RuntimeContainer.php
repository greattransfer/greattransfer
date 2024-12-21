<?php

declare( strict_types=1 );

namespace GreatTransfer\GreatTransfer\Internal\DependencyManagement;

use Automattic\WooCommerce\Utilities\StringUtil;

class RuntimeContainer {

	const GREATTRANSFER_NAMESPACE = 'GreatTransfer\\GreatTransfer\\';

	protected array $resolved_cache;

	protected array $initial_resolved_cache;

	public function __construct( array $initial_resolved_cache ) {
		$this->initial_resolved_cache = $initial_resolved_cache;
		$this->resolved_cache         = $initial_resolved_cache;
	}

	public function get( string $class_name ) {
		$class_name    = trim( $class_name, '\\' );
		$resolve_chain = array();
		return $this->get_core( $class_name, $resolve_chain );
	}

	protected function get_core( string $class_name, array &$resolve_chain ) {
		if ( isset( $this->resolved_cache[ $class_name ] ) ) {
			return $this->resolved_cache[ $class_name ];
		}

		if ( in_array( $class_name, $resolve_chain, true ) ) {
			throw new ContainerException( esc_html( "Recursive resolution of class '$class_name'. Resolution chain: " . implode( ', ', $resolve_chain ) ) );
		}

		if ( ! $this->is_class_allowed( $class_name ) ) {
			throw new ContainerException( esc_html( "Attempt to get an instance of class '$class_name', which is not in the " . self::GREATTRANSFER_NAMESPACE . ' namespace. Did you forget to add a namespace import?' ) );
		}

		if ( ! class_exists( $class_name ) ) {
			throw new ContainerException( esc_html( "Attempt to get an instance of class '$class_name', which doesn't exist." ) );
		}

		$resolve_chain[] = $class_name;

		try {
			$instance = $this->instantiate_class_using_reflection( $class_name, $resolve_chain );
		} catch ( \ReflectionException $e ) {
			throw new ContainerException( esc_html( "Reflection error when resolving '$class_name': (" . get_class( $e ) . ") {$e->getMessage()}" ), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->resolved_cache[ $class_name ] = $instance;

		return $instance;
	}

	private function instantiate_class_using_reflection( string $class_name, array &$resolve_chain ): object {
		$ref_class = new \ReflectionClass( $class_name );
		$instance  = $ref_class->newInstance();
		if ( ! $ref_class->hasMethod( 'init' ) ) {
			return $instance;
		}

		$init_method = $ref_class->getMethod( 'init' );
		if ( ! $init_method->isPublic() || $init_method->isStatic() ) {
			return $instance;
		}

		$init_args          = $init_method->getParameters();
		$init_arg_instances = array_map(
			function ( \ReflectionParameter $arg ) use ( $class_name, &$resolve_chain ) {
				$arg_type = $arg->getType();
				if ( ! ( $arg_type instanceof \ReflectionNamedType ) ) {
					throw new ContainerException( esc_html( "Error resolving '$class_name': argument '\${$arg->getName()}' doesn't have a type declaration." ) );
				}
				if ( $arg_type->isBuiltin() ) {
					throw new ContainerException( esc_html( "Error resolving '$class_name': argument '\${$arg->getName()}' is not of a class type." ) );
				}
				if ( $arg->isPassedByReference() ) {
					throw new ContainerException( esc_html( "Error resolving '$class_name': argument '\${$arg->getName()}' is passed by reference." ) );
				}
				return $this->get_core( $arg_type->getName(), $resolve_chain );
			},
			$init_args
		);

		$init_method->invoke( $instance, ...$init_arg_instances );

		return $instance;
	}

	public function has( string $class_name ): bool {
		$class_name = trim( $class_name, '\\' );
		return $this->is_class_allowed( $class_name ) || isset( $this->resolved_cache[ $class_name ] );
	}

	protected function is_class_allowed( string $class_name ): bool {
		return StringUtil::starts_with( $class_name, self::GREATTRANSFER_NAMESPACE, false );
	}

	public static function should_use(): bool {
		$should_use = ! defined( 'GREATTRANSFER_USE_OLD_DI_CONTAINER' ) || true !== GREATTRANSFER_USE_OLD_DI_CONTAINER;

		return apply_filters( 'greattransfer_use_old_di_container', $should_use );
	}
}
