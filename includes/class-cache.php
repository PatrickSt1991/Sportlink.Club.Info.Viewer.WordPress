<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Transient cache for upstream data plus a file cache for team logos.
 *
 * Every cache key is salted with `scv_cache_salt`; saving any plugin setting
 * (or pressing "Cache legen") bumps the salt, which invalidates everything at once.
 */
class SCV_Cache {

    const SALT_OPTION = 'scv_cache_salt';
    const LOGO_DIR    = 'scv-logos';

    public static function init() {
        add_action( 'updated_option', [ __CLASS__, 'maybe_bump' ] );
        add_action( 'added_option',   [ __CLASS__, 'maybe_bump' ] );
    }

    public static function maybe_bump( $option ) {
        if ( strpos( $option, 'scv_' ) !== 0 ) return;
        if ( in_array( $option, [ self::SALT_OPTION, 'scv_connection_status' ], true ) ) return;
        self::bump();
    }

    public static function salt(): string {
        $salt = get_option( self::SALT_OPTION, '' );
        if ( $salt === '' ) {
            $salt = (string) time();
            update_option( self::SALT_OPTION, $salt, false );
        }
        return (string) $salt;
    }

    /** Invalidate all cached upstream data. */
    public static function bump() {
        update_option( self::SALT_OPTION, (string) microtime( true ), false );
    }

    public static function key( string $name, array $params = [] ): string {
        return 'scv_' . md5( self::salt() . '|' . $name . '|' . wp_json_encode( $params ) );
    }

    /**
     * Return cached data for ($name, $params) or load it with $loader and cache for $ttl seconds.
     * WP_Error results are not cached.
     *
     * @return array [mixed $data, bool $hit]
     */
    public static function remember( string $name, array $params, int $ttl, callable $loader ): array {
        $key    = self::key( $name, $params );
        $cached = get_transient( $key );
        if ( $cached !== false ) {
            return [ $cached, true ];
        }

        $data = $loader();
        if ( ! is_wp_error( $data ) ) {
            set_transient( $key, $data, $ttl );
        }
        return [ $data, false ];
    }

    // ── Logo file cache ──────────────────────────────────────────────────────

    public static function logo_dir(): array {
        $upload = wp_upload_dir();
        return [
            'path' => trailingslashit( $upload['basedir'] ) . self::LOGO_DIR,
            'url'  => trailingslashit( $upload['baseurl'] ) . self::LOGO_DIR,
        ];
    }

    private static function logo_name( string $bucket, string $hash ): string {
        return sanitize_file_name( $bucket . '-' . $hash );
    }

    /** URL of an already cached logo, or null. */
    public static function logo_url( string $bucket, string $hash ) {
        $dir  = self::logo_dir();
        $base = self::logo_name( $bucket, $hash );
        foreach ( [ 'png', 'jpg', 'gif', 'webp', 'svg' ] as $ext ) {
            if ( file_exists( "{$dir['path']}/{$base}.{$ext}" ) ) {
                return "{$dir['url']}/{$base}.{$ext}";
            }
        }
        return null;
    }

    /** Store logo bytes; returns the public URL or null when the uploads dir is not writable. */
    public static function store_logo( string $bucket, string $hash, string $body, string $mime ) {
        $ext_map = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/jpg'     => 'jpg',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        ];
        $mime = strtolower( trim( explode( ';', $mime )[0] ) );
        $ext  = isset( $ext_map[ $mime ] ) ? $ext_map[ $mime ] : 'png';

        $dir = self::logo_dir();
        if ( ! wp_mkdir_p( $dir['path'] ) ) {
            return null;
        }
        if ( ! file_exists( $dir['path'] . '/index.php' ) ) {
            @file_put_contents( $dir['path'] . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        $file = $dir['path'] . '/' . self::logo_name( $bucket, $hash ) . '.' . $ext;
        if ( @file_put_contents( $file, $body ) === false ) {
            return null;
        }
        return $dir['url'] . '/' . self::logo_name( $bucket, $hash ) . '.' . $ext;
    }

    /** Remove every cached logo file. */
    public static function clear_logos() {
        $dir = self::logo_dir();
        if ( ! is_dir( $dir['path'] ) ) return;
        foreach ( (array) glob( $dir['path'] . '/*' ) as $file ) {
            if ( basename( $file ) !== 'index.php' ) {
                @unlink( $file );
            }
        }
    }
}
