<?php

if (!defined('ASTRAPATH'))
    define('ASTRAPATH', dirname(dirname(__FILE__)) . '/');

if (!defined('CZ_DB_PATH')) {
    define('CZ_DB_PATH', dirname(dirname(__FILE__)) . '/');
}

if (!file_exists(CZ_DB_PATH . "db/logging.err")) {
    if (!file_exists(CZ_DB_PATH . 'db')) {
        echo_debug('Creating Folder DB');
        mkdir(CZ_DB_PATH . 'db', 0755, true);
        chmod(CZ_DB_PATH . 'db', 0755);
        echo_debug('DB directory created');

        if (is_writeable(CZ_DB_PATH . 'db')) {
            echo_debug('DB Folder is writable');
        } else {
            echo_debug('DB Folder is not writable');
        }

        $ht = file_put_contents(CZ_DB_PATH . "db/.htaccess", "Order deny,allow\nDeny from all", LOCK_EX);
        if ($ht !== FALSE) {
            echo_debug('.htaccess file created');
        } else {
            echo_debug('Unable to create .htaccess file');
        }
    }
    file_put_contents(CZ_DB_PATH . "db/logging.err", "error logs\n", LOCK_EX);
}

if (file_exists(ASTRAPATH . "astra-config.php")) {
    require_once(ASTRAPATH . "astra-config.php");
    echo_debug('Astra Config Included');
} else {
    echo_debug('Astra Config does not exist');
    file_put_contents(CZ_DB_PATH . 'db/logging.err', "astra-config.php not found\n", LOCK_EX | FILE_APPEND);
    die();
}

/**
 * Class SQLiteWrapper
 */
if (!class_exists('SQLiteWrapper')) {

    class SQLiteWrapper
    {

        /**
         * @var null
         */
        private $error_logging;
        private $_db = null;
//private $_default_timezone = null;

        private $_db_name_file = null;
        private $_db_name = null;
        private $_is_open = false;
        private $_blocking_threshold;
        private $_blocking_duration;

        /**
         * SQLiteLogger constructor.
         */
        function __construct($cz_lvl)
        {
            $this->_blocking_threshold = $cz_lvl[CZ_CONFIG_LVL]['ip_blocking']['count'];
            $this->_blocking_duration = $cz_lvl[CZ_CONFIG_LVL]['ip_blocking']['duration'];
            $this->error_logging = CZ_DB_PATH . 'db/logging.err';
            $this->_open_connection();
        }

        private function _open_connection()
        {
            if ($this->_is_open) {
                return;
            }

            $this->_db_name = CZ_DB_PATH . 'db/' . CZ_DATABASE_NAME . '.db';


            echo_debug($this->_db_name);
            if (!file_exists($this->_db_name)) {
                $create_tables = TRUE;
                echo_debug('Creating SQL Tables');
            } else {
                $create_tables = FALSE;
                echo_debug('Tables have to be created');
            }

            try {
                $this->_db = new PDO('sqlite:' . $this->_db_name);
                echo_debug('PDO Init');
                if ($create_tables) {
                    $this->_create_tables();
                }
                $this->_is_open = true;
                return;
            } catch (PDOException $e) {
                echo_debug('Unable to init DB');
                file_put_contents($this->error_logging, "cannot initialize database\n", LOCK_EX | FILE_APPEND);
                $this->_is_open = false;
                $this->_db = null;
                return;
            }
        }

        /**
         *
         * @return bool
         */
        private function _create_tables()
        {
            try {
                $query = array();

                $query[] = "CREATE TABLE IF NOT EXISTS ip_logs (
                    id INTEGER PRIMARY KEY,
                    ip_address TEXT UNIQUE,
                    request_count INTEGER DEFAULT 0,
                    request_count_since_blocking INTEGER DEFAULT 0,
                    blocked_by_ids INTEGER DEFAULT 0,
                    blocked_by_user INTEGER DEFAULT 0,
                    trusted_by_user INTEGER DEFAULT 0,
                    timestamp INTEGER
                  );";

                $query[] = "CREATE TABLE IF NOT EXISTS exception_params (
                    id INTEGER PRIMARY KEY,
                    param TEXT UNIQUE,
                    type TEXT,
                    timestamp INTEGER
                  );";

                $query[] = "CREATE TABLE IF NOT EXISTS exception_url (
                    id INTEGER PRIMARY KEY,
                    url TEXT UNIQUE,
                    timestamp INTEGER
                  );";

                $query[] = "CREATE TABLE IF NOT EXISTS exception_ip_ranges (
                    id INTEGER PRIMARY KEY,
                    ip_range TEXT UNIQUE,
                    trusted_by_user INTEGER DEFAULT 0,
                    timestamp INTEGER
                  );";

                $query[] = "CREATE TABLE IF NOT EXISTS bad_bots (
                    id INTEGER PRIMARY KEY,
                    bot TEXT UNIQUE,
                    from_honeypot INTEGER DEFAULT 0,
                    timestamp INTEGER
                  );";

                $query[] = "CREATE TABLE IF NOT EXISTS token_bucket (
                    id INTEGER PRIMARY KEY,
                    ip_address TEXT UNIQUE,
                    last_api_request TEXT,
                    throttle_minute TEXT,
                    reported INTEGER DEFAULT 0
                  );";

                foreach ($query as $q)
                    $this->_db->exec($q);

                echo_debug('.htaccess file created');
                return true;
            } catch (PDOException $e) {
                file_put_contents($this->error_logging, "cannot create tables in db", LOCK_EX | FILE_APPEND);
                return false;
            }
        }

        private function _close_connection()
        {
            $this->_db = null;
            $this->_is_open = false;
            echo_debug('Closing connection');
        }

        /**
         * @return null
         */
        function insert_into_ip_logs($data)
        {
            if (isset($data)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                if (isset($data['trusted_by_user'])) {
                    $query = "INSERT INTO ip_logs (ip_address, request_count, request_count_since_blocking, blocked_by_ids,
                  blocked_by_user, trusted_by_user, timestamp) VALUES (:ip_address, :request_count, :request_count_since_blocking,
                  :blocked_by_ids, :blocked_by_user, :trusted_by_user, strftime('%s', 'now'));";
                } else {
                    $query = "INSERT INTO ip_logs (ip_address, request_count, request_count_since_blocking, blocked_by_ids,
                  blocked_by_user, timestamp) VALUES (:ip_address, :request_count, :request_count_since_blocking,
                  :blocked_by_ids, :blocked_by_user, strftime('%s', 'now'));";
                }

                $statement = $this->_db->prepare($query);


                if (!$statement->execute($data)) {
                    file_put_contents($this->error_logging, "\n\ncannot execute query: {$query} {$this->_db->errorInfo()}", LOCK_EX | FILE_APPEND);
                    return false;
                }
                $this->_close_connection();
                return true;
            }
        }

        function get_ip_log($ip_address)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }
                $query = "SELECT * FROM ip_logs WHERE ip_address = :ip_address LIMIT 1;";
                $statement = $this->_db->prepare($query);
                $statement->execute(array('ip_address' => $ip_address));
                $statement->setFetchMode(PDO::FETCH_ASSOC);
                $result_row = $statement->fetch();
                return $result_row;
            }
        }

        function unblock_ip($ip_address, $by_user = false, $by_ids = false, $trusted = false)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                if ($by_user) {

                    if ($trusted) {
                        $update = ',trusted_by_user = 0';
                    } else {
                        $update = ",blocked_by_user = 0";
                    }
                }
                if ($by_ids) {
                    $update = ",blocked_by_ids = 0";
                }

                $query = "UPDATE ip_logs SET timestamp = 0 {$update}, request_count = 0 WHERE ip_address = '{$ip_address}';";

                if (!$this->_db->exec($query)) {
                    file_put_contents($this->error_logging, "unblock_ip - cannot execute query: {$query}", LOCK_EX | FILE_APPEND);
                    return false;
                }

                $this->_close_connection();
                return TRUE;
            }

            return TRUE;
        }

        function is_trusted($ip_address)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                $query = "SELECT * FROM ip_logs WHERE ip_address = :ip_address;";

                $statement = $this->_db->prepare($query);
                if ($statement->execute(array('ip_address' => $ip_address))) {
                    $statement->setFetchMode(PDO::FETCH_ASSOC);
                    $result = $statement->fetch();

                    if ($result['trusted_by_user'] == 1) {
                        return TRUE;
                    } else {
                        return FALSE;
                    }
                }
            }
        }

        function is_blocked($ip_address, $is_bot = FALSE)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                $query = "SELECT * FROM ip_logs WHERE ip_address = :ip_address;";

                $statement = $this->_db->prepare($query);
                if ($statement->execute(array('ip_address' => $ip_address))) {
                    $statement->setFetchMode(PDO::FETCH_ASSOC);
                    $result = $statement->fetch();

                    if ($result['trusted_by_user'] == 1)
                        return FALSE;

                    if ($result['blocked_by_user'] == 1) {
                        return true;
                    }

                    $this->_blocking_duration = 0.1;

                    if ($is_bot) {
                        $duration = $cz_lvl[CZ_CONFIG_LVL]['bot_blocking']['duration'];;
                    } else {
                        $duration = $this->_blocking_duration;
                    }

                    if ($result['blocked_by_ids'] == 1) {
                        if (time() - $result['timestamp'] >= $duration * 60) {
                            $this->unblock_ip($ip_address, false, true);
                            return false;
                        }
                        return TRUE;
                    }
                }
            }
        }

        function is_bad_bot($user_agent)
        {
            if (isset($user_agent)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                try {
                    $query = "SELECT * FROM bad_bots WHERE '{$user_agent}' LIKE '%' || bot || '%';";

                    $statement = $this->_db->prepare($query);

                    if ($statement->execute()) {
                        $statement->setFetchMode(PDO::FETCH_ASSOC);
                        $result = $statement->fetch();
                        //print_r($result);

                        if (!empty($result)) {
                            return TRUE;
                        }
                    }
                    return FALSE;
                } catch (PDOException $e) {
                    return FALSE;
                }
            }
        }

        protected function truncate_table($table_name)
        {
            try {

                $this->_db->beginTransaction();

                $query = array();
                $query[] = "DELETE from {$table_name};";
                $query[] = "VACUUM;";

                foreach ($query as $q) {
                    $stmt = $this->_db->prepare($q);
                    $stmt->execute();
                }

                $this->_db->commit();

                return TRUE;
            } catch (PDOException $e) {
                return FALSE;
            }
        }

        function update_bad_bots($bots)
        {
            if (isset($bots)) {
                echo_debug('Bots are set');
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                try {

                    if ($this->truncate_table('bad_bots')) {

                        $this->_db->beginTransaction();
                        $query = $this->_db->prepare("INSERT INTO bad_bots (bot, timestamp) VALUES (?, strftime('%s', 'now'))");
                        foreach ($bots as $bot) {
                            $query->bindParam(1, $bot);
                            $query->execute();
                        }
                        $this->_db->commit();
                        echo_debug('BOTS Updated');
                        return TRUE;
                    } else {
                        echo_debug('Unable to truncate BOTS Table');
                        return FALSE;
                    }
                } catch (PDOException $e) {
                    echo_debug('PDO Error while updating BOTS');
                    return FALSE;
                }
            } else {
                echo_debug('Bots not set');
            }
        }

        function update_custom_params($params)
        {
            if (isset($params)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                if (empty($params)) {
                    return TRUE;
                }

                try {

                    if ($this->truncate_table('exception_params')) {

                        $this->_db->beginTransaction();
                        $query = $this->_db->prepare("INSERT INTO exception_params (param, type, timestamp) VALUES (?, ?, strftime('%s', 'now'))");
                        foreach ($params as $p) {
                            $query->bindParam(1, $p[1]);
                            $query->bindParam(2, $p[0]);
                            $query->execute();
                        }
                        $this->_db->commit();

                        return TRUE;
                    } else {
                        return FALSE;
                    }
                } catch (PDOException $e) {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }

        function update_custom_ip($ips)
        {
            if (isset($ips)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }
                try {

                    if (!empty($ips)) {
                        //$this->_db->beginTransaction();
                        foreach ($ips as $ip) {
                            $type_bool = ($ip[0] == "trusted") ? TRUE : FALSE;
                            $this->update_ip_logs($ip[1], TRUE, $type_bool);
                        }
                        //$this->_db->commit();
                        return TRUE;
                    } else {
                        return TRUE;
                    }
                } catch (PDOException $e) {
                    return FALSE;
                }
            }
        }

        function update_ip_logs($ip_address, $user = false, $trusted = FALSE, $is_bot = FALSE)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                $data = array('ip_address' => $ip_address);

                if ($is_bot) {
                    $threshold = $cz_lvl[CZ_CONFIG_LVL]['bot_blocking']['count'];
                } else {
                    $threshold = $this->_blocking_threshold;
                }

                $field = "blocked_by_ids";
                $conditions = "AND request_count >={$threshold}";

                if ($user) {
                    $row = $this->get_ip_log($ip_address);
                    if (empty($row)) {
                        $data = array(
                            'ip_address' => $ip_address,
                            'request_count' => 0,
                            'request_count_since_blocking' => 0,
                            'blocked_by_ids' => 0,
                            'blocked_by_user' => ($trusted) ? 0 : 1,
                            'trusted_by_user' => (int)$trusted,
                        );
                        return $this->insert_into_ip_logs($data);
                    }

                    if (!$trusted) {
                        $field = "trusted_by_user=0, blocked_by_user";
                        $conditions = "";
                    } else {
                        $field = "blocked_by_user=0, trusted_by_user";
                        $conditions = "";
                    }
                }

                $query = "UPDATE ip_logs SET {$field} = 1, timestamp = strftime('%s', 'now'), request_count_since_blocking=request_count_since_blocking+1 WHERE ip_address = :ip_address {$conditions};";


                $statement = $this->_db->prepare($query);

                if ($statement->execute($data)) {
                    $this->_close_connection();
                    return TRUE;
                } else {
                    file_put_contents($this->error_logging, "cannot execute query: {$query}", LOCK_EX | FILE_APPEND);
                    return FALSE;
                }
            }
        }

        function log_hit($ip_address, $blocked = false)
        {
            if (isset($ip_address)) {
                $row = $this->get_ip_log($ip_address);
                if (empty($row)) {
                    $data = array(
                        'ip_address' => $ip_address,
                        'request_count' => 0,
                        'request_count_since_blocking' => 0,
                        'blocked_by_ids' => 0,
                        'blocked_by_user' => 0,
                    );
                    $this->insert_into_ip_logs($data);
                }

                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                if ($blocked) {
                    $field = "request_count_since_blocking";
                } else {
                    $field = "request_count";
                }

                $query = "UPDATE ip_logs SET {$field} = {$field} + 1 WHERE ip_address = '{$ip_address}'";
                $statement = $this->_db->prepare($query);

                if ($statement->execute()) {
                    $this->update_ip_logs($ip_address);
                    $this->_close_connection();
                    return true;
                } else {
                    file_put_contents($this->error_logging, "cannot execute update query: {$query}", LOCK_EX | FILE_APPEND);
                    return false;
                }
            }
        }

        function insert_into_table($table_name, $data, $timestamp = TRUE)
        {
            if (isset($data)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }


                try {
                    $columns = "";
                    $values = "";
                    foreach ($data as $key => $val) {
                        $columns .= $key . ', ';
                        $values .= ':' . $key . ', ';
                    }

                    if ($timestamp) {
                        $query = "INSERT INTO {$table_name} ({$columns}timestamp) VALUES ({$values}strftime('%s', 'now'));";
                    } else {
                        $columns = rtrim($columns, ', ');
                        $values = rtrim($values, ', ');
                        $query = "INSERT INTO {$table_name} ({$columns}) VALUES ({$values});";
                    }

                    echo_debug($query);

                    //echo $query;

                    $statement = $this->_db->prepare($query);

                    $statement->execute($data);
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        return TRUE;
                    }

                    file_put_contents($this->error_logging, "cannot execute query: {$query}", LOCK_EX | FILE_APPEND);
                    return false;
                }
                $this->_close_connection();
                return true;
            }
        }

        function delete_from_table($table_name, $data)
        {
            if (!empty($data)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }


                try {
                    $where = "";
                    foreach ($data as $key => $val) {
                        $where .= "$key='$val' AND ";
                    }

                    $where = rtrim($where, ' AND ');
                    echo_debug($where);

                    $query = "DELETE FROM {$table_name} WHERE {$where};";

                    echo_debug($query);

                    $statement = $this->_db->prepare($query);
                    $res = $statement->execute(array());
                    echo_debug('RES=' . $res . ';');
                    if ($res) {
                        return true;
                    } else {
                        return false;
                    }
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        return TRUE;
                    }

                    file_put_contents($this->error_logging, "cannot execute query: {$query}", LOCK_EX | FILE_APPEND);
                    return false;
                }
                $this->_close_connection();
                return true;
            }
        }

        function edit_ip_exception($action, $type, $ip)
        {
//update_ip_logs($ip_address, $user = false, $trusted = FALSE) {

            $type_bool = ($type == "trusted") ? TRUE : FALSE;

            if ($action == "add") {
                return $this->update_ip_logs($ip, TRUE, $type_bool);
//function update_ip_logs($ip_address, $user = false, $trusted = FALSE) {
            } elseif ($action == "delete") {
                return $this->unblock_ip($ip, TRUE, FALSE, $type_bool);
            } else {
                return FALSE;
            }
        }

        function edit_param_exception($action, $type, $val)
        {
            //$type_bool = ($type == "trusted") ? TRUE : FALSE;

            if ($action == "add") {
                $data = array(
                    'type' => $type,
                    'param' => $val,
                );

                return $this->insert_into_table('exception_params', $data);
            } elseif ($action == "delete") {
                echo_debug(json_encode($this->get_custom_params()));
                $data = array(
                    'type' => $type,
                    'param' => $val,
                );
                return $this->delete_from_table('exception_params', $data);
            } else {
                return FALSE;
            }
        }

        function get_api_request($ip_address)
        {
            if (isset($ip_address)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                $query = "SELECT * FROM token_bucket WHERE ip_address = :ip_address LIMIT 1;";
                $statement = $this->_db->prepare($query);
                $statement->execute(array('ip_address' => $ip_address));
                $statement->setFetchMode(PDO::FETCH_ASSOC);
                $result_row = $statement->fetch();

                if (empty($result_row)) {
                    $ret = array(
                        'last_api_request' => time(),
                        'throttle_minute' => 0,
                        'ip_address' => $ip_address,
                        'reported' => 0
                    );

                    $this->insert_into_table('token_bucket', $ret, FALSE);

                    return $ret;
                } else {
                    return $result_row;
                }
            }
            return FALSE;
        }

        function save_api_request($ip_address, $last_api_request, $throttle_minute, $reported)
        {
            if (isset($ip_address) && isset($last_api_request) && isset($throttle_minute) && isset($reported)) {
                if (!$this->_is_open) {
                    $this->_open_connection();
                }

                $query = "UPDATE token_bucket SET last_api_request = :last_api_request, throttle_minute=:throttle_minute, reported=:reported WHERE ip_address = :ip_address;";

                $data = array(
                    'ip_address' => $ip_address,
                    'last_api_request' => $last_api_request,
                    'throttle_minute' => $throttle_minute,
                    'reported' => $reported,
                );

                $statement = $this->_db->prepare($query);

                if (!$statement->execute($data)) {
                    file_put_contents($this->error_logging, "Save_api_request - cannot execute query: {$query}", LOCK_EX | FILE_APPEND);
                    return false;
                }
                $this->_close_connection();
                return TRUE;
            }
        }

        function block_bot($ip_address)
        {
            if (isset($ip_address)) {
                return $this->update_ip_logs($ip_address, FALSE, FALSE, TRUE);
            }
            return FALSE;
        }

        function get_exception_urls()
        {
            if (!$this->_is_open) {
                $this->_open_connection();
            }

            $query = "SELECT * FROM exception_url;";

            $statement = $this->_db->prepare($query);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);

            $result_rows = $statement->fetchAll();

            $ret = array();

            foreach ($result_rows as $row) {
                $ret[] = $row['url'];
            }

            return $ret;
        }

        function get_custom_params()
        {
            if (!$this->_is_open) {
                $this->_open_connection();
            }

            $query = "SELECT type, param FROM exception_params;";

            $statement = $this->_db->prepare($query);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);

            $result_rows = $statement->fetchAll();

            $ret = array(
                'html' => array(),
                'json' => array(),
                'exception' => array(),
                'url' => array(),
            );

            foreach ($result_rows as $row) {
                $ret[$row['type']][] = $row['param'];
            }

            return $ret;
        }

    }

}