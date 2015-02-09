<?php

/** Radius log unserializer. */
class RadiusLog {

	/** Path of the directory where the log is.
	 * @type string
	 */
	private $logDir;

	/** Constructor.
	 * @param string $logDir Path of the directory where the log is.
	 */
	public function __construct( $logDir ) {
		$this->logDir = $logDir;
	}
	
	/** Get the path of a day file.
	 * @param integer $year Year.
	 * @param integer $month Month.
	 * @param integer $dom Day of the month.
	 */
	private function getPath( $year, $month, $dom ) {
		return $this->logDir . '/detail-' . $year
			. str_pad( $month, 2, '0', STR_PAD_LEFT )
			. str_pad( $dom, 2, '0', STR_PAD_LEFT );
	}

	/** Get the array representation for a day of activity.
	 * @param integer $year Year.
	 * @param integer $month Month.
	 * @param integer $dom Day of the month.
	 */
	private function unserializeDay( $year, $month, $dom ) {
		$path = $this->getPath( $year, $month, $dom );
		$res = array();
		
		if ( file_exists( $path ) ) {
			$content = file_get_contents( $path );
			$items = explode( "\n\n", $content );
		
			$res = array_map(
				function ( $item ) {
					$lines = explode( "\n", $item );
					$itemRepr = array();
	
					for ( $i = 1; $i < count( $lines ); ++$i ) {
						preg_match( '#^\t([^=]+) = "?([^"]+)"?$#', $lines[$i], $m );
						$itemRepr[ $m[1] ] = $m[2]; 
					}

					return $itemRepr;
				},
				$items
			);
			
			/* Remove empty elements */
			$res = array_filter( $res );
		}
		
		return $res;
	}

	/** Get the array representation for a month of activity.
	 * @param integer $year Year.
	 * @param integer $month Month.
	 */
	private function unserializeMonth( $year, $month ) {
		$res = array();
		
		for ( $dom = 1; $dom <= 31; ++$dom ) {
			$res = array_merge( $res, $this->unserializeDay( $year, $month, $dom ) );
		}
		
		return $res;
	}

	/** Remove the SIP prefix from an account identifiant.
	 * @param string $userId The account string.
	 */
	private function removeSIPPrefix( $userId ) {
		if ( substr( $userId, 0, 4 ) === 'sip:' ) {
			$userId = substr( $userId, 4 );
		}
		
		return $userId;
	}

	/** Get the list of calls for a month of activity.
	 * @param integer $year Year.
	 * @param integer $month Month.
	 * @return CallList
	 */
	public function getMonthCalls( $year, $month ) {
		$monthLog = $this->unserializeMonth( $year, $month );
		$started = array();
		$calls = new CallList();
		
		foreach ( $monthLog as $logItem ) {
			$id = $logItem['Acct-Unique-Session-Id'];
			
			switch ( $logItem['Acct-Status-Type'] ) {
				case 'Start':
					$started[$id] = array(
						'caller' => $this->removeSIPPrefix( $logItem['Calling-Station-Id'] ),
						'callee' => $this->removeSIPPrefix( $logItem['Called-Station-Id'] ),
						'start' => (int) $logItem['Timestamp']
					);
					break;
				
				case 'Stop':
					if ( ! array_key_exists( $id, $started ) ) {
						throw new Exception( 'Unstarted call #' . $id );
					}
					
					$calls->add( new EffectiveCall(
						$started[$id]['caller'],
						$started[$id]['callee'],
						$started[$id]['start'],
						(int) $logItem['Timestamp']
					) );
					
					unset( $started[$id] );
					break;
				
				case 'Failed':
					$calls->add( new AvortedCall(
						$this->removeSIPPrefix( $logItem['Calling-Station-Id'] ),
						$this->removeSIPPrefix( $logItem['Called-Station-Id'] ),
						(int) $logItem['Timestamp']
					) );
					break;
				
				default:
					throw new Exception(
						'Unknown status “' . $logItem['Acct-Status-Type'] . '”'
					);
			}
		}
		
		if ( count( $started ) > 0 ) {
			throw new Exception( 'Unstarted call(s) #' . implode( ', #', array_keys( $started ) ) );
		}
		
		return $calls;
	}
	
}
