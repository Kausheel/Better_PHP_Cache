<?php
    class Better_PHP_Cache
    {
        private $cache_files_dir;
        private $monitor_cache_stats;

        public function __construct($cache_files_dir, $monitor_cache_stats = TRUE)
        {
            if($cache_files_dir)
            {
                if(file_exists($cache_files_dir) && is_writable($cache_files_dir))
                {
                    $this->cache_files_dir = $cache_files_dir;
                }
            }

            if($monitor_cache_stats == TRUE)
            {
                $this->monitor_cache_stats = TRUE;
                $cache_stats = apc_fetch('cache_stats');

                if(!$cache_stats)
                {
                    $cache_stats['monitoring_start_timestamp'] = time();
                }
                else
                {
                    $cache_stats['total_monitored_duration_in_seconds'] = time() - $cache_stats['monitoring_start_timestamp'];
                }

                apc_store('cache_stats', $cache_stats);
            }
        }

        public function store($entry_name, $entry_value, $time_to_live, $store_in_filesystem = FALSE)
        {
            if(!($entry_name && $entry_value && $time_to_live))
            {
                return FALSE;
            }

            if($this->monitor_cache_stats == TRUE)
            {
                $cache_stats = apc_fetch($cache_stats);
                $cache_stats[$entry_name]['store_count'] = $cache_stats[$entry_name]['store_count'] + 1;
                apc_store('cache_stats', $cache_stats);
            }

            if($store_in_filesystem == TRUE)
            {
                $this->store_to_filesystem($entry_name, $entry_value, $time_to_live);
            }
            else
            {
                return apc_store($entry_name, $entry_value, $time_to_live);
            }
        }

        public function fetch($entry_name, $fetch_from_filesystem = FALSE)
        {
            if(!$entry_name)
            {
                return FALSE;
            }

            if($fetch_from_filesystem == TRUE)
            {
                return $this->fetch_from_filesystem($entry_name);
            }
            else
            {
                return apc_fetch($entry_name);
            }
        }

        public function delete($entry_name, $delete_from_filesystem = FALSE)
        {
            if(!$entry_name)
            {
                return FALSE;
            }

            if($delete_from_filesystem == TRUE)
            {
                return $this->delete_from_filesystem($entry_name);
            }
            else
            {
                return apc_delete($entry_name);
            }
        }

        public function reset_cache_stats()
        {
            $cache_stats = NULL;
            apc_store('cache_stats', $cache_stats);
        }

        public function copy_entry_to_filesystem($entry_name)
        {

        }

        public function copy_entry_to_memory($entry_name)
        {

        }

        public function copy_all_entries_to_filesystem()
        {

        }

        public function copy_all_entries_to_memory()
        {

        }

        public function get_cache_entry_stats()
        {

        }

        private function store_in_filesystem($entry_name, $entry_value, $time_to_live)
        {

        }

        private function fetch_from_filesystem($entry_name)
        {

        }

        private function delete_from_filesystem($entry_name)
        {

        }
    }
?>