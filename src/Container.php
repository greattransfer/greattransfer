<?php

declare( strict_types=1 );

namespace GreatTransfer\GreatTransfer;

use GreatTransfer\GreatTransfer\Internal\DependencyManagement\RuntimeContainer;

final class Container {

	private $container;

	public function __construct() {
		if ( RuntimeContainer::should_use() ) {
			$this->container = new RuntimeContainer(
				array(
					__CLASS__                          => $this,
					'Psr\Container\ContainerInterface' => $this,
				)
			);
			return;
		}

		$this->container = new ExtendedContainer();

		$this->container->share( __CLASS__, $this );

		foreach ( $this->get_service_providers() as $service_provider_class ) {
			$this->container->addServiceProvider( $service_provider_class );
		}
	}

	public function get( string $id ) {
		return $this->container->get( $id );
	}

	public function has( string $id ): bool {
		return $this->container->has( $id );
	}

	private function get_service_providers(): array {
		return array();
	}
}
