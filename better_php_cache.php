<?php
    class Better_PHP_Cache
    {
        public function __construct()
        {

        }

        public function store($entry_name, $entry_value, $time_to_live)
        {
            if(!($entry_name && $entry_value && $time_to_live))
            {
                return FALSE;
            }

            return apc_store($entry_name, $entry_value, $time_to_live);
        }

        public function fetch($entry_name)
        {
            if(!$entry_name)
            {
                return FALSE;
            }

            return apc_fetch($entry_name);
        }

        public function delete($entry_name)
        {

        }
    }
?>