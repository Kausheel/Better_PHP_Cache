<?php
    class Better_PHP_Cache
    {
        private $monitor_cache_stats;

        public function __construct($cache_files_dir, $monitor_cache_stats = FALSE)
        {
            //Check if a cache directory has been specified, and is writable.
            if($cache_files_dir)
            {
                if(file_exists($cache_files_dir) && is_writable($cache_files_dir))
                {
                    chdir($cache_files_dir);
                }
            }

            //Check if cache statistics should be gathered.
            if($monitor_cache_stats == TRUE)
            {
                $this->begin_cache_monitoring();
            }
        }

        //Store cache entry in memory by default, or optionally store in filesystem.
        public function store($entry_name, $entry_value, $time_to_live = FALSE, $store_in_filesystem = FALSE)
        {
            if(!($entry_name && $entry_value))
            {
                return FALSE;
            }

            //Track how often this entry has been stored.
            if($this->monitor_cache_stats == TRUE)
            {
                $this->increment_cache_stats_store_count($entry_name);
            }

            if($store_in_filesystem == TRUE)
            {
                return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
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

            //Track how often this entry has been fetched.
            if($this->monitor_cache_stats == TRUE)
            {
                $this->increment_cache_stats_fetch_count($entry_name);
            }

            if($fetch_from_filesystem == TRUE)
            {
                $entry = $this->fetch_from_filesystem($entry_name);
                $entry_value = $entry['data'];
            }
            else
            {
                $entry_value = apc_fetch($entry_name);
            }

            //Track how often a cache fetch 'misses'.
            if($this->monitor_cache_stats == TRUE && !$entry_value)
            {
                $this->increment_cache_stats_miss_count($entry_name);
            }

            return $entry_value;
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
                //APC checks entry expiries when an entry is fetched. Fetching all entries will automatically clear the expired ones.
                $this->fetch_all_from_memory();
            }
            else
            {
                //When a filesystem entry is fetched, its expiry time is checked and the entry is deleted if necessary.
                //Therefore, we don't need to run another delete function. Just fetching every entry is enough to remove the expired ones.
                $this->fetch_all_from_filesystem();
            }
        }

        //Refresh an entry's TTL to prevent expiration.
        public function refresh_entry_ttl($entry_name, $time_to_live = FALSE, $entry_in_filesystem = FALSE)
        {
            if(!$entry_name)
            {
                return FALSE;
            }

            if($entry_in_filesystem == TRUE)
            {
                $entry = $this->fetch_from_filesystem($entry_name);
                $entry_value = $entry['data'];

                if($entry_value)
                {
                    return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
                }
            }
            else
            {
                $entry_value = apc_fetch($entry_name);

                if($entry_value)
                {
                    return apc_store($entry_name, $entry_value, $time_to_live);
                }
            }

            return FALSE;
        }

        //Remove existing cache statistics data.
        public function reset_cache_stats()
        {
            $cache_stats = NULL;
            apc_store('cache_stats', $cache_stats);
        }

        //Copy an entry from memory to the filesystem, and optionally remove the original copy.
        public function copy_entry_to_filesystem($entry_name, $delete_from_memory = FALSE)
        {
            $entry_value = apc_fetch($entry_name);

            if(!$entry_value)
            {
                return FALSE;
            }

            $time_to_live = $this->fetch_ttl_from_memory($entry_name);

            if($time_to_live == 'expired')
            {
                return FALSE;
            }

            if($delete_from_memory == TRUE)
            {
                apc_delete($entry_name);
            }

            return $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);
        }

        //Copy an entry from the filesystem to memory, and optionally remove the original copy.
        public function copy_entry_to_memory($entry_name, $delete_from_filesystem = FALSE)
        {
            $filesystem_entry = $this->fetch_from_filesystem($entry_name);
            $filesystem_entry_value = $filesystem_entry['data'];

            if($delete_from_filesystem == TRUE)
            {
                $this->delete_from_filesystem($entry_name);
            }

            return apc_store($entry_name, $filesystem_entry_value, $filesystem_entry['expiry']);
        }

        //Copy all entries from memory to the filesystem.
        //Optionally, delete the original entries after copying.
        public function copy_all_entries_to_filesystem($delete_from_memory_after_copy = FALSE)
        {
            $memory_entry_array = $this->fetch_all_from_memory();

            $ttl_array = $this->fetch_every_ttl_from_memory();

            if(!$memory_entry_array || !$ttl_array)
            {
                return FALSE;
            }

            foreach ($memory_entry_array as $entry_name => $entry_value)
            {
                $time_to_live = $ttl_array[$entry_name];

                $this->store_in_filesystem($entry_name, $entry_value, $time_to_live);

                if($delete_from_memory_after_copy == TRUE)
                {
                    $this->delete($entry_name);
                }
            }
        }

        //Copy all entries from the filesystem to memory.
        //Optionally, delete the original entries after copying.
        public function copy_all_entries_to_memory($delete_from_filesystem_after_copy = FALSE)
        {
            $filesystem_entry_array = $this->fetch_all_from_filesystem();

            if($filesystem_entry_array)
            {
                foreach($filesystem_entry_array as $entry_name => $entry_value)
                {
                    apc_store($entry_name, $entry_value['data'], $entry_value['expiry']);

                    if($delete_from_filesystem_after_copy == TRUE)
                    {
                        $this->delete_from_filesystem($entry_name);
                    }
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
            if(!$this->monitor_cache_stats)
            {
                return FALSE;
            }

            $cache_stats = apc_fetch('cache_stats');

            $most_fetched_entry = $this->find_most_fetched_entry();
            $most_stored_entry = $this->find_most_stored_entry();
            $total_monitored_duration_in_seconds = $this->fetch_total_cache_monitoring_time();

            $cache_stats['most_fetched_entry'] = $most_fetched_entry['name'];
            $cache_stats['most_stored_entry'] = $most_stored_entry['name'];
            $cache_stats['total_monitored_duration_in_seconds'] = $total_monitored_duration_in_seconds;

            apc_store('cache_stats', $cache_stats);

            return $cache_stats;
        }

        public function find_most_fetched_entry()
        {
            $cache_stats = apc_fetch('cache_stats');

            if(!$cache_stats)
            {
                return FALSE;
            }

            //Cycle through each cache entry
            foreach($cache_stats as $cache_entry => $cache_data)
            {
                if(is_array($cache_data))
                {
                    //Some cache entries are created by this class, not by the user. Don't cycle through those.
                    if(array_key_exists('fetch_count', $cache_data))
                    {
                        //Find the cache entry with the highest fetch_count.
                        if($cache_data['fetch_count'] > $most_fetched_entry['count'])
                        {
                            $most_fetched_entry['count'] = $cache_data['fetch_count'];
                            $most_fetched_entry['name'] = $cache_entry;
                        }
                    }
                }
            }

            return $most_fetched_entry;
        }

        public function find_most_stored_entry()
        {
            $cache_stats = apc_fetch('cache_stats');

            if(!$cache_stats)
            {
                return FALSE;
            }

            //Cycle through each cache entry
            foreach($cache_stats as $cache_entry => $cache_data)
            {
                if(is_array($cache_data))
                {
                    if(array_key_exists('store_count', $cache_data))
                    {
                        //Find the cache entry with the highest store_count.
                        if($cache_data['store_count'] > $most_stored_entry['count'])
                        {
                            $most_stored_entry['count'] = $cache_data['store_count'];
                            $most_stored_entry['name'] = $cache_entry;
                        }
                    }
                }
            }

            return $most_stored_entry;
        //Clear out the filesystem cache entries.
        public function delete_all_from_filesystem()
        {
            //Get a list of all cache files.
            $cache_files_array = scandir(getcwd());

            //Remove the '.' and '..' entries added by default by the scandir() function.
            $cache_files_array = array_diff($cache_files_array, array('..', '.'));

            //Cycle through each file name.
            foreach ($cache_files_array as $file)
            {
                unlink($file);
            }
        }

        private function begin_cache_monitoring()
        {
            $this->monitor_cache_stats = TRUE;
            $cache_stats = apc_fetch('cache_stats');

            if(!$cache_stats['monitoring_start_timestamp'])
            {
                $cache_stats['monitoring_start_timestamp'] = time();
                apc_store('cache_stats', $cache_stats);
            }
        }

        private function fetch_total_cache_monitoring_time()
        {
            $cache_stats = apc_fetch('cache_stats');
            $total_monitored_duration_in_seconds = time() - $cache_stats['monitoring_start_timestamp'];

            return $total_monitored_duration_in_seconds;
        }

        private function fetch_ttl_from_memory($entry_name)
        {
            $apc_cache_info = apc_cache_info();
            $cache_array = $apc_cache_info['cache_list'];

            //Cycle through the cache entries to search for a TTL.
            //This TTL needs to be transferred to the filesystem entry.
            foreach($cache_array as $entry)
            {
                if($entry['key'] == $entry_name)
                {
                    //Calculate expiry time by adding the TTL with the creation time.
                    $time_to_live = $entry['ttl'] + $entry['ctime'];

                    //Don't bother copying the cache entry if it has expired.
                    if($time_to_live <= time())
                    {
                        return 'expired';
                    }

                    break;
                }
            }

            return $time_to_live;
        }

        //Get the expiry times of memory cache entries
        private function fetch_every_ttl_from_memory()
        {
            $apc_cache_info = apc_cache_info();
            $entry_info_array = $apc_cache_info['cache_list'];

            if(!$entry_info_array)
            {
                return FALSE;
            }

            foreach($entry_info_array as $cache_entry)
            {
                //For each entry, calculate the expiry time by adding the TTL with the creation time.
                $expiry_time = $cache_entry['ttl'] + $cache_entry['ctime'];
                $time_to_live = $expiry_time - time();

                //If the entry hasn't expired, store the TTL.
                if($time_to_live >= 0)
                {
                    $new_cache_array[$cache_entry['key']]['ttl'] = $time_to_live;
                }
            }

            return $new_cache_array;
        }

        private function increment_cache_stats_miss_count($cache_entry)
        {
            $cache_stats = apc_fetch('cache_stats');
            $cache_stats[$cache_entry]['miss_count'] = $cache_stats[$cache_entry]['miss_count'] + 1;
            apc_store('cache_stats', $cache_stats);
        }

        private function increment_cache_stats_store_count($cache_entry)
        {
            $cache_stats = apc_fetch('cache_stats');
            $cache_stats[$cache_entry]['store_count'] = $cache_stats[$cache_entry]['store_count'] + 1;
            apc_store('cache_stats', $cache_stats);
        }

        private function increment_cache_stats_fetch_count($cache_entry)
        {
            $cache_stats = apc_fetch('cache_stats');
            $cache_stats[$cache_entry]['fetch_count'] = $cache_stats[$cache_entry]['fetch_count'] + 1;
            apc_store('cache_stats', $cache_stats);
        }

        //Fetch all cache entries from memory.
        private function fetch_all_from_memory()
        {
            $cache_info = apc_cache_info('user');

            foreach($cache_info['cache_list'] as $cache_entry)
            {
                $entry_name = $cache_entry['key'];
                $all_entries[$entry_name] = apc_fetch($entry_name);
            }

            //Check if there is at least 1 entry.
            if($all_entries)
            {
                return $all_entries;
            }
            else
            {
                return FALSE;
            }
        }

        //Store entry to filesystem.
        private function store_in_filesystem($entry_name, $entry_value, $time_to_live)
        {
            //If no TTL is supplied, the entry will not expire.
            if(!$time_to_live)
            {
                $expiry = FALSE;
            }
            else
            {
                $expiry = $time_to_live + time();
            }

            //Append the cache data with an expiry field, and store it as a JSON array.
            $cache_data = array('data' => $entry_value, 'expiry' => $expiry);
            $cache_data = json_encode($cache_data);

            return file_put_contents($entry_name, $cache_data);
        }

        //Fetch cache entry from filesystem.
        private function fetch_from_filesystem($entry_name)
        {
            if(file_exists($entry_name))
            {
                $cache_data = file_get_contents($entry_name);
            }
            else
            {
                return FALSE;
            }

            //Convert the file data into an associative JSON array.
            $cache_data = json_decode($cache_data, TRUE);

            //If the cache entry has expired, delete it.
            //Ignore FALSE expiry values because they indicate a non-expiring entry.
            if(($cache_data['expiry'] < time()) && $cache_data['expiry'] > 0)
            {
                $this->delete_from_filesystem($entry_name);
                return FALSE;
            }

            //If the number is a UNIX timestamp (as opposed to FALSE), then subtract the current time to get the TTL (measured in seconds).
            if($cache_data['expiry'] > time())
            {
                $cache_data['expiry'] = $cache_data['expiry'] - time();
            }

            return $cache_data;
        }

        private function fetch_all_from_filesystem()
        {
            //Get a list of all cache files.
            $cache_files_array = scandir(getcwd());

            //Remove the '.' and '..' entries added by default by the scandir() function.
            $cache_files_array = array_diff($cache_files_array, array('..', '.'));

            //Cycle through each file name.
            foreach ($cache_files_array as $file)
            {
                $decoded_file_contents = $this->fetch_from_filesystem($file);

                if($decoded_file_contents)
                {
                    //Add the cache entry to an array.
                    $cache_data_array[$file] = $decoded_file_contents;
                }
            }

            //Check that there is at least 1 cache entry.
            if($cache_data_array)
            {
                return $cache_data_array;
            }
            else
            {
                return FALSE;
            }
        }

        //Delete the cache entry from the filesystem.
        private function delete_from_filesystem($entry_name)
        {
            if(file_exists($entry_name))
            {
                return unlink($entry_name);
            }
            else
            {
                return FALSE;
            }
        }
    }
?>