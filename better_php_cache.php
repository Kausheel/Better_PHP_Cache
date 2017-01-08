<?php
    class Better_PHP_Cache
    {
        private $cache_files_dir;

        public function __construct($cache_files_dir)
        {
            if($cache_files_dir)
            {
                if(file_exists($cache_files_dir) && is_writable($cache_files_dir))
                {
                    $this->cache_files_dir = $cache_files_dir;
                }
            }
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
            if($entry_name)
            {
                return apc_delete($entry_name);
            }
        }
    }
?>