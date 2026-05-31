<?php
	class dbConnection {
		protected static $instance = null;

		public static function connect() {
			try {			
				if (static::$instance === null) {
					$ini = parse_ini_file("ehr.ini", true);
					$hostname = $ini["database"]["hostname"];
					$username = $ini["database"]["username"];
					$passw = $ini["database"]["passw"];

                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,						
                    ];                    

					static::$instance = new PDO($hostname, $username, $passw, $options);
				}
			
			} catch (PDOException $e) {
				static::$instance = $e->getMessage();
			}
			
			return static::$instance;
		}
	}
?>	