<?php
/**
 * Settings manager.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes plugin settings stored in wp_options.
 */
class TR_AI_Toolkit_Settings {

	private const OPTION_KEY = 'tr_ai_toolkit_settings';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	/**
	 * Returns the full settings array, merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$saved       = get_option( self::OPTION_KEY, array() );
			$this->cache = $this->deep_merge(
				TR_AI_Toolkit_Activator::default_settings(),
				is_array( $saved ) ? $saved : array()
			);
		}

		return $this->cache;
	}

	/**
	 * Returns a dot-notation setting value, e.g. 'permissions.read_capability'.
	 *
	 * @param string $key     Dot-separated key path.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$parts   = explode( '.', $key );
		$current = $this->all();

		foreach ( $parts as $part ) {
			if ( ! is_array( $current ) || ! array_key_exists( $part, $current ) ) {
				return $default;
			}
			$current = $current[ $part ];
		}

		return $current;
	}

	/**
	 * Updates a top-level settings group.
	 *
	 * @param string               $group  Top-level key (e.g. 'permissions').
	 * @param array<string, mixed> $values New values to merge into the group.
	 */
	public function update_group( string $group, array $values ): void {
		$settings          = $this->all();
		$settings[ $group ] = array_merge(
			$settings[ $group ] ?? array(),
			$values
		);
		update_option( self::OPTION_KEY, $settings );
		$this->cache = null;
	}

	/**
	 * Saves the complete settings array.
	 *
	 * @param array<string, mixed> $settings Full settings array.
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings );
		$this->cache = null;
	}

	/**
	 * Recursively merges $override into $base.
	 *
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, mixed>
	 */
	private function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
