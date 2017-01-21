<?php
    class Better_PHP_Cache
    {
        private $cache_files_dir;
        private $monitor_cache_stats;

        public function __construct($cache_files_dir, $monitor_cache_stats = FALSE)
        {
            //Check if a cache directory has been specified, and is writable.
            if($cache_files_dir)
            {
                if(file_exists($cache_files_dir) && is_writable($cache_files_dir))
                {
                    $this->cache_files_dir = $cache_files_dir;
                }
            }

            //Check if cache statistics should be gathered.
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

        //Store cache entry in memory by default, or optionally store in filesystem.
        public function store($entry_name, $entry_value, $time_to_live, $store_in_filesystem = FALSE)
        {
            if(!($entry_name && $entry_value && $time_to_live))
            {
                return FALSE;
            }

            if($this->monitor_cache_stats == TRUE)
            {
                //Track how often this cache entry name was stored.
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

        //Fetch entry from memory by default, or optionally from filesystem.
        public function fetch($entry_name, $fetch_from_filesystem = FALSE)
        {
            if(!$entry_name)
            {
                return FALSE;
            }

            if($this->monitor_cache_stats == TRUE)
            {
                //Track how often this cache entry was accessed.
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

        //Delete entry from memory by default, or optionally delete from filesystem.
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

        //Delete expired entries from filesystem.
        //Entries in memory are already automatically cleared by APC, but expired filesystem objects need to be manually cleared.
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
                //When a filesystem entry is fetched, its expiry time is checked and the entry is deleted if necessary.
                //Therefore, we don't need to run another delete function. Just fetching every entry is enough to remove the expired ones.
                $this->fetch_all_from_filesystem();
            }
        }

        //Refresh an entry's TTL to prevent expiration.
        public function refresh_entry_ttl($entry_name, $time_to_live, $entry_in_filesystem = FALSE)
        {
            if(!($entry_name && $time_to_live))
            {
                return FALSE;
            }

            if($entry_in_filesystem == TRUE)
            {
                $entry_value = $this->fetch_from_filesystem($entry_name);
                return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
            }
            else
            {
                $entry_value = apc_fetch($entry_name);
                return apc_store($entry_name, $entry_value, $time_to_live);
            }
        }

        //Remove existing cache statistics data.
        public function reset_cache_stats()
        {
            $cache_stats = NULL;
            apc_store('cache_stats', $cache_stats);
        }

        //Copy an entry from memory to the filesystem, and optionally remove the original copy.
        public function copy_entry_to_filesystem($entry_name, $time_to_live, $delete_from_memory = FALSE)
        {
            $entry_value = apc_fetch($entry_name);

            if($delete_from_memory == TRUE)
            {
                apc_delete($entry_name);
            }

            return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
        }

        //Copy an entry from the filesystem to memory, and optionally remove the original copy.
        public function copy_entry_to_memory($entry_name, $new_time_to_live, $delete_from_filesystem = FALSE)
        {
            $filesystem_entry_value = $this->fetch_from_filesystem($entry_name);

            if($delete_from_filesystem == TRUE)
            {
                $this->delete_from_filesystem($entry_name);
            }

            return apc_store($entry_name, $filesystem_entry_value, $new_time_to_live);
        }

        //Copy all entries from memory to the filesystem.
        public function copy_all_entries_to_filesystem()
        {
            $memory_entry_array = $this->fetch_all_from_memory();

            foreach ($memory_entry_array as $entry_name => $entry_value)
            {
                $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
            }
        }

        //Copy all entries from the filesystem to memory.
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

        //Fetch cache statistics.
        public function fetch_cache_stats()
        {
            return apc_fetch('cache_stats');
        }

        private function fetch_all_from_memory()
        {

        }

        //Store entry to filesystem.
        private function store_in_filesystem($entry_name, $entry_value, $time_to_live)
        {
            $expiry = $time_to_live + time();

            //Append the cache data with an expiry field, and store it as a JSON array.
            $cache_data = array('data' => $entry_value, 'expiry' => $expiry);
            $cache_data = json_encode($cache_data);

            return file_put_contents($entry_name, $cache_data);
        }

        //Fetch cache entry from filesystem.
        private function fetch_from_filesystem($entry_name)
        {
            $cache_data = file_get_contents($entry_name);

            //Convert the file data into an associative JSON array.
            $cache_data = json_decode($cache_data, TRUE);

            //If the cache entry has expired, delete it.
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

        //Delete the cache entry from the filesystem.
        private function delete_from_filesystem($entry_name)
        {
            return unlink($entry_name);
        }
    }
?>