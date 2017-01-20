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

            if($this->monitor_cache_stats == TRUE)
            {
                $cache_stats = apc_fetch('cache_stats');
                $cache_stats[$entry_name]['fetch_count'] = $cache_stats[$entry_name]['fetch_count'] + 1;
                apc_store('cache_stats', $cache_stats);
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

        public function delete_expired_entries($purge_from_filesystem = FALSE)
        {
            if($purge_from_filesystem == FALSE)
            {
                $entries_in_memory = $this->fetch_all_from_memory();

                foreach($entries_in_memory as $entry)
                {
                    if(($entry['time_to_live'] + $entry['creation_timestamp']) < time())
                    {
                        $this->delete_from_memory($entry['name']);
                    }
                }
            }
            else
            {
                $entries_in_filesystem = $this->fetch_all_from_filesystem();

                foreach($entries_in_filesystem as $entry)
                {
                    if(($entry['time_to_live'] + $entry['creation_timestamp']) < time())
                    {
                        $this->delete_from_filesystem($entry['name']);
                    }
                }
            }
        }

        public function refresh_entry_ttl($entry_name, $time_to_live, $entry_in_filesystem = FALSE)
        {
            if(!($entry_name && $time_to_live))
            {
                return FALSE;
            }

            if($entry_in_filesystem == TRUE)
            {
                $entry_value = $this->fetch_from_filesystem($entry_name);
                $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
            }
            else
            {
                $entry_value = apc_fetch($entry_name);
                apc_store($entry_name, $entry_value, $time_to_live);
            }
        }

        public function reset_cache_stats()
        {
            $cache_stats = NULL;
            apc_store('cache_stats', $cache_stats);
        }

        public function copy_entry_to_filesystem($entry_name, $time_to_live, $delete_from_memory = FALSE)
        {
            $entry_value = apc_fetch($entry_name);

            if($delete_from_memory == TRUE)
            {
                apc_delete($entry_name);
            }

            return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
        }

        public function copy_entry_to_memory($entry_name, $new_time_to_live, $delete_from_filesystem = FALSE)
        {
            $filesystem_entry_value = $this->fetch_from_filesystem($entry_name);

            if($delete_from_filesystem == TRUE)
            {
                $this->delete_from_filesystem($entry_name);
            }

            return apc_store($entry_name, $filesystem_entry_value, $new_time_to_live);
        }

        public function copy_all_entries_to_filesystem()
        {
            $memory_entry_array = $this->fetch_all_from_memory();

            foreach ($memory_entry_array as $entry_name => $entry_value)
            {
                $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
            }
        }

        public function copy_all_entries_to_memory()
        {
            $filesystem_entry_array = $this->fetch_all_from_filesystem();

            if($filesystem_entry_array)
            {
                foreach($filesystem_entry_array as $entry_name => $entry_value)
                {
                    apc_store($entry_name, $entry_value);
                }

                return TRUE;
            }
            else
            {
                return FALSE;
            }
        }

        public function fetch_cache_stats()
        {
            return apc_fetch('cache_stats');
        }

        private function fetch_all_from_memory()
        {

        }

        private function store_in_filesystem($entry_name, $entry_value, $time_to_live)
        {
            $expiry = $time_to_live + time();

            $cache_data = array('data' => $entry_value, 'expiry' => $expiry);
            $cache_data = json_encode($cache_data);

            return file_put_contents($entry_name, $cache_data);
        }

        private function fetch_from_filesystem($entry_name)
        {
            $cache_data = file_get_contents($entry_name);
            $cache_data = json_decode($cache_data, TRUE);

            if($cache_data['expiry'] < time())
            {
                $this->delete_from_filesystem($entry_name);
                return FALSE;
            }
            else
            {
                return $cache_data['data'];
            }
        }

        private function fetch_all_from_filesystem()
        {

        }

        private function delete_from_filesystem($entry_name)
        {
            return unlink($entry_name);
        }
    }
?>