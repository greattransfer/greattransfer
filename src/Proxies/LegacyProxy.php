<?php

namespace GreatTransfer\GreatTransfer\Proxies;

use GreatTransfer\GreatTransfer\Internal\DependencyManagement\Definition;
use GreatTransfer\GreatTransfer\Utilities\StringUtil;
use GreatTransfer\GreatTransfer\Vendor\Psr\Container\ContainerInterface;

class LegacyProxy {

	public function get_instance_of( string $class_name, ...$args ) {
		if ( StringUtil::starts_with( $class_name, 'GreatTransfer\\GreatTransfer\\' ) ) {
			throw new \Exception(
				esc_html(
					'The LegacyProxy class is not intended for getting instances of classes whose namespace starts with \'GreatTransfer\\GreatTransfer\', please use ' .
					Definition::INJECTION_METHOD . ' method injection or the instance of ' . ContainerInterface::class . ' for that.'
				)
			);
		}

		$method = 'get_instance_of_' . strtolower( $class_name );
		if ( method_exists( __CLASS__, $method ) ) {
			return $this->$method( ...$args );
		}

		if ( method_exists( $class_name, 'instance' ) ) {
			return $class_name::instance( ...$args );
		}

		if ( method_exists( $class_name, 'load' ) ) {
			return $class_name::load( ...$args );
		}

		return new $class_name( ...$args );
	}

	private function get_instance_of_wc_queue_interface() {
		return \WC_Queue::instance();
	}

	public function call_function( $function_name, ...$parameters ) {
		return call_user_func_array( $function_name, $parameters );
	}

	public function call_static( $class_name, $method_name, ...$parameters ) {
		return call_user_func_array( "$class_name::$method_name", $parameters );
	}

	public function get_global( string $global_name ) {
		return $GLOBALS[ $global_name ];
	}

	public function exit( $status = '' ) {
		exit( esc_html( $status ) );
	}
}
