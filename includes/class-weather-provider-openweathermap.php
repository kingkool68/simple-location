<?php

class Weather_Provider_OpenWeatherMap extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openweathermap_api' );
		}
		if ( ! isset( $args['station_id'] ) ) {
			$args['station_id'] = get_option( 'sloc_openweathermap_id' );
		}
		parent::__construct( $args );

	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		$data   = array(
			'appid' => $this->api,
			'units' => $this->temp_units,
		);
		$return = array( 'units' => $this->temp_unit() );
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$url         = 'http://api.openweathermap.org/data/2.5/weather?';
			$data['lat'] = $this->latitude;
			$data['lon'] = $this->longitude;
			$url         = $url . build_query( $data );
			$response    = wp_remote_get( $url );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( isset( $response['main'] ) ) {
				$return['temperature'] = $response['main']['temp'];
				$return['humidity']    = $response['main']['humidity'];
				$return['pressure']    = $response['main']['pressure'];
			}
			if ( isset( $response['wind'] ) ) {
				$return['wind']           = array();
				$return['wind']['speed']  = $response['wind']['speed'];
				$return['wind']['degree'] = $response['wind']['deg'];
			}
			if ( isset( $response['weather'] ) ) {
				if ( wp_is_numeric_array( $response['weather'] ) ) {
					$response['weather'] = $response['weather'][0];
				}
				$return['summary'] = $response['weather']['description'];
				$return['icon']    = $this->icon_map( (int) $response['weather']['id'] );
			}
			if ( isset( $response['visibility'] ) ) {
				$return['visibility'] = $response['visibility'];
			}
			if ( isset( $response['precipitation'] ) ) {
				$return['precipitation'] = $response['precipitation']['mode'];
				if ( 'no' === $return['precipitation'] ) {
					unset( $return['precipitation'] );
				}
				$return['precipitation_value'] = $return['precipitation']['value'];
			}
			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		if ( $this->station_id ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->station_id ) );
				if ( $conditions ) {
					return $conditions;
				}
			}

			$url                = 'http://api.openweathermap.org/data/3.0/measurements?';
			$data['station_id'] = $this->station_id;
			$data['type']       = 'h';
			// An hour ago
			$data['from']  = current_time( 'timestamp' ) - 3600;
			$data['to']    = current_time( 'timestamp' );
			$data['limit'] = '1';
			$url           = $url . build_query( $data );
			$response      = wp_remote_get( $url );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( wp_is_numeric_array( $response ) ) {
				$response = $response[0];
			}
			if ( isset( $response['temp'] ) ) {
				$return['temperature'] = $response['temp']['average'];
				// OpenWeatherMap doesn't allow you to set fahrenheit in this API
				if ( 'imperial' === $this->temp_units ) {
					$return['temperature'] = $this->metric_to_imperial( $return['temperature'] );
				}
			}
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = $response['humidity']['average'];
			}
			if ( isset( $response['wind'] ) ) {
				$return['wind']           = array();
				$return['wind']['speed']  = $response['wind']['speed'];
				$return['wind']['degree'] = $response['wind']['deg'];
			}
			if ( isset( $response['pressure'] ) ) {
				$return['pressure'] = $response['pressure']['average'];
			}
			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->station_id ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id ) {
		if ( in_array( $id, array( 200, 201, 202, 230, 231, 232 ), true ) ) {
			return 'wi-thunderstorm';
		}
		if ( in_array( $id, array( 210, 211, 212, 221 ), true ) ) {
			return 'wi-lightning';
		}
		if ( in_array( $id, array( 300, 301, 321, 500 ), true ) ) {
			return 'wi-sprinkle';
		}
		if ( in_array( $id, array( 302, 311, 312, 314, 501, 502, 503, 504 ), true ) ) {
			return 'wi-rain';
		}
		if ( in_array( $id, array( 310, 511, 611, 612, 615, 616, 620 ), true ) ) {
			return 'wi-rain-mix';
		}
		if ( in_array( $id, array( 313, 520, 521, 522, 701 ), true ) ) {
			return 'wi-showers';
		}
		if ( in_array( $id, array( 531, 901 ), true ) ) {
			return 'wi-storm-showers';
		}
		if ( in_array( $id, array( 600, 601, 621, 622 ), true ) ) {
			return 'wi-snow';
		}
		if ( in_array( $id, array( 602 ), true ) ) {
			return 'wi-sleet';
		}

		if ( in_array( $id, array( 711 ), true ) ) {
			return 'wi-smoke';
		}
		if ( in_array( $id, array( 721 ), true ) ) {
			return 'wi-day-haze';
		}
		if ( in_array( $id, array( 731, 761 ), true ) ) {
			return 'wi-dust';
		}
		if ( in_array( $id, array( 741 ), true ) ) {
			return 'wi-fog';
		}
		if ( in_array( $id, array( 771, 801, 802, 803 ), true ) ) {
			return 'wi-cloudy-gusts';
		}
		if ( in_array( $id, array( 781, 900 ), true ) ) {
			return 'wi-tornado';
		}
		if ( in_array( $id, array( 800 ), true ) ) {
			return 'wi-day-sunny';
		}
		if ( in_array( $id, array( 804 ), true ) ) {
			return 'wi-cloudy';
		}
		if ( in_array( $id, array( 902, 962 ), true ) ) {
			return 'wi-hurricane';
		}
		if ( in_array( $id, array( 903 ), true ) ) {
			return 'wi-snowflake-cold';
		}
		if ( in_array( $id, array( 904 ), true ) ) {
			return 'wi-hot';
		}
		if ( in_array( $id, array( 905 ), true ) ) {
			return 'wi-windy';
		}
		if ( in_array( $id, array( 906 ), true ) ) {
			return 'wi-day-hail';
		}
		if ( in_array( $id, array( 957 ), true ) ) {
			return 'wi-strong-wind';
		}
		if ( in_array( $id, array( 762 ), true ) ) {
			return 'wi-volcano';
		}
		if ( in_array( $id, array( 751 ), true ) ) {
			return 'wi-sandstorm';
		}
		return '';
	}

}
