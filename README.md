#A PHP Caching library extending APC's functionality with a filesystem cache

###Features

Having an easy way to sync the APC memory cache with the filesystem has multiple uses.
1. By default, APC has no way to share data between a web server process and a PHP CLI process, so having a way to read/write data to a filesystem gives you a bridge between APC on both processes. This is useful if you use PHP scripts for background tasks, and want to use a single cache between both the web server and the CLI. The fact that the filesystem is slower than memory shouldn't have any noticeable affect on your application, since you'll only interact with the filesystem when a sync is necessary. After that point, each process can just read from its own memory cache.

2. A filesystem cache can be helpful if you are storing massive data sets which take up too much memory. The penalty of the slower filesystem cache can be worth it in exchange for avoiding a large MySQL query, for example.

3. A filesystem cache allows persistence in case of a server reboot. Once a server reboots and loses its memory cache, it may take time to rebuild to its original state. Having your memory cache routinely backing up to a filesystem allows you to easily recover from a server reboot by transferring the entire cache contents back into memory with a single function call.

Another small feature of this library is that it tracks various cache statistics. For example, you can easily find out which of your cache entries experience the most 'cache misses', which may help you improve your cache management strategy.

###API Summary
- store($entry_name, $entry_value, $time_to_live, $store_in_filesystem = FALSE)
- fetch($entry_name, $fetch_from_filesystem = FALSE)
- delete($entry_name, $delete_from_filesystem = FALSE)
- copy_entry_to_filesystem($entry_name, $time_to_live, $delete_from_memory = FALSE)
- copy_entry_to_memory($entry_name, $new_time_to_live, $delete_from_filesystem = FALSE)
- copy_all_entries_to_filesystem($delete_from_memory_after_copy = FALSE)
- copy_all_entries_to_memory($delete_from_filesystem_after_copy)
- delete_expired_entries($purge_from_filesystem = FALSE)
- refresh_entry_ttl($entry_name, $time_to_live, $entry_in_filesystem = FALSE)
- fetch_cache_stats()
- reset_cache_stats()

###Usage

---
    $cache = new Better_PHP_Cache($cache_files_dir, $monitor_cache_stats = FALSE)
The constructor for this class takes two parameters. The first specifies where you want to store your filesystem cache entries. You could just create a directory on your web server called 'cache', and then specify 'cache' as the first argument. This function will automatically check to make sure the cache directory is writable, and show an error if it is not.

The second argument (optionally) allows you to track statistics about your cache entries. These statistics include how often each entry has been stored, fetched, or missed. Cache misses are particularly important to track, because a cache miss is a performance hit on your application, so you may consider storing these entries with longer expiries. Since cache statistics tracking takes *slightly* more resources, it is disabled by default. However, you should enable it if you are testing your application and want to gather performance information.

---
    $cache->store('entry_name', 'entry_value', 60)
The above code stores a cache entry into the APC memory cache for a duration of 60 seconds.

If you wanted to store an entry into the filesystem instead, you simply add a fourth parameter:

    $cache->store('entry_name', 'entry_value', 60, TRUE)
The above code stores your entry into the cache files directory that you specified in the class constructor. It won't be stored in the APC memory cache.

---

    $cache->fetch('entry_name');
By default, the fetch() function fetches the cache entry from memory. If you want to fetch an entry from the filesystem, use:

    $cache->fetch('entry_name', TRUE);
The above code will fetch directly from the filesystem.

---

    $cache->delete('entry_name');

The delete() function will delete entries from memory by default. If you want to delete entries from the filesystem, use:

    $cache->delete('entry_name', TRUE);

---

    $cache->copy_entry_to_filesystem('entry_name', 60)

The above code copies an entry from memory to the filesystem, and sets a new 'time_to_live' in seconds.

If you want to copy an entry to the filesystem and then delete the original copy from memory, then use:

    $cache->copy_entry_to_filesystem('entry_name', 60, TRUE)

---

    $cache->copy_entry_to_memory('entry_name', 60)

The above code copies an entry from the filesystem cache to the memory cache, and set a new 'time_to_live' in seconds.

If you want to copy an entry to memory and then delete the original copy from the filesystem, then use:

    $cache->copy_entry_to_memory('entry_name', 60, TRUE)

---

    $cache->copy_all_entries_to_filesystem()

The above code copies all memory cache entries to the filesystem cache. If you want to delete the original entries from memory after copying, use:

    $cache->copy_all_entries_to_filesystem(TRUE)

---

    $cache->copy_all_entries_to_memory()

The above code copies all filesystem cache entries to memory. If you want to delete the original entries from memory, use:

    $cache->copy_all_entries_to_memory(TRUE)

---

    $delete_expired_entries()

The above code deletes any expired cache entries from memory only. This is generally not necessary since both PHP and your operating system will intelligently handle memory usage anyway.

However, this function is very important for cleaning up the filesystem cache. This library automatically checks the filesystem cache expiries when fetching an entry, and deletes accordingly. But if a filesystem entry is never fetched again, then you'll have useless expired cache entries taking up space permanently.

The filesystem cache should be cleaned once in a while to prevent large amounts of junk data from filling it up. Use the following function:

    $delete_expired_entries(TRUE)

The above code will clear out expired filesystem cache entries (while leaving the memory cache intact).

---

    $cache->refresh_entry_ttl('entry_name', 60)

The above code refreshes the expiry of a cache entry in memory. Most of the time, you would probably just use the store() function to reapply the TTL. However, store() requires you to provide the actual cache entry value as a parameter, which may not always be convenient. For example, you may have to re-run a large SQL query to re-store() your cache data.

Instead, you could just use the above refresh function to reapply a new TTL to an entry, just by providing its name. You don't need to have the data on hand to refresh the TTL unlike store(), therefore it can be much more convenient to use.

If you want to refresh a TTL for a cache entry in the filesystem, use:

    $cache->refresh_entry_ttl('entry_name', 60, TRUE)

It's important to note that you cannot refresh the TTL of an entry which has already expired.

---

    $cache->fetch_cache_stats()

The above code will fetch statistics about each cache entry, such as how many times each entry was fetched, stored, or 'missed'. It will return FALSE if you didn't switch on cache monitoring when the cache object was first created.

---

    $cache->reset_cache_stats()

The above code simply wipes the cache stats data. This may be useful if you have made changes to your application, and want to restart the cache monitoring process with a clean slate.