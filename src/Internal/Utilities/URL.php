<?php

namespace GreatTransfer\GreatTransfer\Internal\Utilities;

class URL {

	private $components = array(
		'drive'    => null,
		'fragment' => null,
		'host'     => null,
		'pass'     => null,
		'path'     => null,
		'port'     => null,
		'query'    => null,
		'scheme'   => null,
		'user'     => null,
	);

	private $is_absolute;

	private $is_non_root_directory;

	private $path_parts = array();

	private $url;

	public function __construct( string $url ) {
		$this->url = $url;
		$this->preprocess();
		$this->process_path();
	}

	private function preprocess() {
		$this->url = str_replace( '\\', '/', $this->url );

		if ( preg_match( '#^(file://)?([a-z]):/(?!/).*#i', $this->url, $matches ) ) {
			$this->components['drive'] = $matches[2];
		}

		if ( ! preg_match( '#^[a-z]+://#i', $this->url ) && ! preg_match( '#^//(?!/)#', $this->url ) ) {
			$this->url = 'file://' . $this->url;
		}

		$parsed_components = wp_parse_url( $this->url );

		if ( false === $parsed_components ) {
			throw new URLException(
				esc_html(
					sprintf(
					/* translators: %s is the URL. */
						__( '%s is not a valid URL.', 'greattransfer' ),
						$this->url
					)
				)
			);
		}

		$this->components = array_merge( $this->components, $parsed_components );

		if ( 'file' === $this->components['scheme'] && ! empty( $this->components['host'] ) ) {
			if ( null === $this->components['drive'] ) {
				$this->components['path'] = $this->components['host'] . ( $this->components['path'] ?? '' );
			}

			$this->components['host'] = null;
		}
	}

	private function process_path() {
		$segments                    = explode( '/', $this->components['path'] );
		$this->is_absolute           = substr( $this->components['path'], 0, 1 ) === '/' || ! empty( $this->components['host'] );
		$this->is_non_root_directory = substr( $this->components['path'], -1, 1 ) === '/' && strlen( $this->components['path'] ) > 1;
		$resolve_traversals          = 'file' !== $this->components['scheme'] || $this->is_absolute;
		$retain_traversals           = false;

		foreach ( $segments as $part ) {
			if ( strlen( $part ) === 0 || '.' === $part ) {
				continue;
			}

			$is_traversal = str_ireplace( '%2e', '.', $part ) === '..';

			if ( $resolve_traversals && $is_traversal ) {
				if ( count( $this->path_parts ) > 0 && ! $retain_traversals ) {
					$this->path_parts = array_slice( $this->path_parts, 0, count( $this->path_parts ) - 1 );
					continue;
				} elseif ( $this->is_absolute ) {
					continue;
				}
			}

			if ( false === $resolve_traversals && '..' !== $part && 'file' === $this->components['scheme'] && ! $this->is_absolute ) {
				$resolve_traversals = true;
			}

			$retain_traversals = $resolve_traversals && '..' === $part;

			$this->path_parts[] = $part;
		}

		if ( count( $this->path_parts ) === 0 && ! $this->is_absolute ) {
			$this->path_parts            = array( '.' );
			$this->is_non_root_directory = true;
		}

		$this->components['path'] = ( $this->is_absolute ? '/' : '' ) . implode( '/', $this->path_parts ) . ( $this->is_non_root_directory ? '/' : '' );
	}

	public function __toString(): string {
		return $this->get_url();
	}

	public function get_all_parent_urls(): array {
		$max_parent = count( $this->path_parts );
		$parents    = array();

		if ( $max_parent > 0 && ! $this->is_absolute && '..' === $this->path_parts[0] ) {
			$max_parent = 1;
		}

		for ( $level = 1; $level <= $max_parent; $level++ ) {
			$parents[] = $this->get_parent_url( $level );
		}

		return $parents;
	}

	public function get_parent_url( int $level = 1 ) {
		if ( $level < 1 ) {
			$level = 1;
		}

		$parts_count               = count( $this->path_parts );
		$parent_path_parts_to_keep = $parts_count - $level;

		if ( 'file' !== $this->components['scheme'] && $parent_path_parts_to_keep < 0 ) {
			return false;
		}

		if ( 'file' === $this->components['scheme'] && $this->is_absolute && empty( $this->path_parts ) ) {
			return false;
		}

		if ( $parts_count > 0 && ( '.' === $this->path_parts[0] || '..' === $this->path_parts[0] ) ) {
			$single_dots   = array_keys( $this->path_parts, '.', true );
			$double_dots   = array_keys( $this->path_parts, '..', true );
			$max_dot_index = max( array_merge( $single_dots, $double_dots ) );

			$last_traversal = $max_dot_index + ( $this->is_non_root_directory ? 1 : 0 );
			$parent_path    = str_repeat( '../', $level ) . join( '/', array_slice( $this->path_parts, 0, $last_traversal ) );
		} elseif ( $parent_path_parts_to_keep < 0 ) {
			$parent_path = untrailingslashit( str_repeat( '../', $parent_path_parts_to_keep * -1 ) );
		} else {
			$parent_path = implode( '/', array_slice( $this->path_parts, 0, $parent_path_parts_to_keep ) );
		}

		if ( $this->is_relative() && '' === $parent_path ) {
			$parent_path = '.';
		}

		$parent_path .= '/';

		if ( $this->is_absolute && 0 !== strpos( $parent_path, '/' ) ) {
			$parent_path = '/' . $parent_path;
		}

		$parent_url = $this->get_url(
			array(
				'path'     => $parent_path,
				'query'    => null,
				'fragment' => null,
			)
		);

		return ( new self( $parent_url ) )->get_url();
	}

	public function get_url( array $component_overrides = array() ): string {
		$components = array_merge( $this->components, $component_overrides );

		$scheme = null !== $components['scheme'] ? $components['scheme'] . '://' : '//';
		$host   = null !== $components['host'] ? $components['host'] : '';
		$port   = null !== $components['port'] ? ':' . $components['port'] : '';
		$path   = $this->get_path( $components['path'] );

		if ( '' === $host && ( '' === $path || '.' === $path ) ) {
			$path = './';
		}

		$user      = null !== $components['user'] ? $components['user'] : '';
		$pass      = null !== $components['pass'] ? ':' . $components['pass'] : '';
		$user_pass = ( ! empty( $user ) || ! empty( $pass ) ) ? $user . $pass . '@' : '';

		$query    = null !== $components['query'] ? '?' . $components['query'] : '';
		$fragment = null !== $components['fragment'] ? '#' . $components['fragment'] : '';

		return $scheme . $user_pass . $host . $port . $path . $query . $fragment;
	}

	public function get_path( ?string $path_override = null ): string {
		return ( $this->components['drive'] ? $this->components['drive'] . ':' : '' ) . ( $path_override ?? $this->components['path'] );
	}

	public function is_absolute(): bool {
		return $this->is_absolute;
	}

	public function is_relative(): bool {
		return ! $this->is_absolute;
	}
}
