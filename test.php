<?php
    include('better_php_cache.php');

    require_once 'PHPUnit/Autoload.php';

    final class cache_test extends PHPUnit_Framework_TestCase
    {
        private $cache;

        public function setUp()
        {
            $this->cache = new Better_PHP_Cache('cache', TRUE);
        }

        public function tearDown()
        {
            @$this->cache->delete_all_from_filesystem();
            apc_clear_cache();
        }

        public function test_store_and_fetch_from_memory()
        {
            @$this->cache->store('key_1', 'value_1');
            $this->assertEquals('value_1', @$this->cache->fetch('key_1'));
        }

        public function test_store_and_fetch_from_filesystem()
        {
            @$this->cache->store('key_2', 'value_2', 5, TRUE);
            $this->assertEquals('value_2', @$this->cache->fetch('key_2', TRUE));
        }

        public function test_storing_memory_ttl()
        {
            @$this->cache->store('key_1', 'value_1', 5);

            $apc_cache_info = apc_cache_info();
            $cache_array = $apc_cache_info['cache_list'];

            $this->assertEquals(5, $cache_array[0]['ttl']);
        }

        public function test_storing_filesystem_ttl()
        {
            @$this->cache->store('key_3', 'value_3', 2, TRUE);

            $file_contents = file_get_contents('key_3');
            $decoded = json_decode($file_contents, TRUE);

            $this->assertEquals(time() + 2, $decoded['expiry']);
        }

        public function test_fetching_expired_filesystem_entry()
        {
            $file = json_encode(array('data' => 'test_data', 'expiry' => time() - 2));
            file_put_contents('test_entry', $file);

            $this->assertNull(@$this->cache->fetch('test_entry', TRUE));
        }

        public function test_delete_from_memory()
        {
            @$this->cache->store('key_4', 'value_4');
            $this->cache->delete('key_4');
            $this->assertFalse(@$this->cache->fetch('key_4'));
        }

        public function test_delete_from_filesystem()
        {
            @$this->cache->store('key_5', 'value_5', 5, TRUE);
            $this->cache->delete('key_5', TRUE);
            $this->assertNull(@$this->cache->fetch('key_5', TRUE));
        }

        public function test_delete_expired_entries_from_filesystem()
        {
            $file_1 = json_encode(array('data' => 'test', 'expiry' => time() - 1));
            $file_2 = json_encode(array('data' => 'test2', 'expiry' => time() - 2));

            file_put_contents('key_5', $file_1);
            file_put_contents('key_6', $file_2);

            @$this->cache->delete_expired_entries(TRUE);

            $this->assertNull(@$this->cache->fetch('key_5', TRUE));
            $this->assertNull(@$this->cache->fetch('key_6', TRUE));
        }

        public function test_refresh_entry_ttl_from_memory()
        {
            @$this->cache->store('key_1', 'value_1', 5);
            @$this->cache->refresh_entry_ttl('key_1', 10);

            $apc_cache_info = apc_cache_info();
            $cache_array = $apc_cache_info['cache_list'];

            $this->assertEquals(10, $cache_array[0]['ttl']);
        }

        public function test_refresh_entry_ttl_from_filesystem()
        {
            @$this->cache->store('key_1', 'value_1', 5, TRUE);
            @$this->cache->refresh_entry_ttl('key_1', 10, TRUE);

            $file = file_get_contents('key_1');
            $decoded = json_decode($file, TRUE);

            $this->assertEquals(time() + 10, $decoded['expiry']);
        }

        public function test_fetch_cache_stats()
        {
            @$this->cache->store('key_7', 'value_7');
            @$this->cache->fetch('key_7', 'value_7');
            @$cache_stats = $this->cache->fetch_cache_stats();
            $this->assertTrue($cache_stats['key_7']['store_count'] > 0);
            $this->assertTrue($cache_stats['key_7']['fetch_count'] > 0);
        }

        public function test_reset_cache_stats()
        {
            @$this->cache->store('key_8', 'value_8');
            @$this->cache->reset_cache_stats();

            //@$cache_stats = $this->cache->fetch_cache_stats();
            //$this->assertFalse($cache_stats['key_8']['stored_count'] > 0);
            //Fix reset_cache_stats()
        }

        public function test_copy_entry_to_filesystem()
        {
            @$this->cache->store('key_9', 'value_9', 10);
            $this->cache->copy_entry_to_filesystem('key_9', TRUE);

            $this->assertEquals('value_9', @$this->cache->fetch('key_9', TRUE));
            $this->assertFalse(@$this->cache->fetch('key_9'));

            $file_contents = file_get_contents('key_9');
            $decoded = json_decode($file_contents, TRUE);
            $this->assertEquals(time() + 10, $decoded['expiry']);
        }

        public function test_copy_entry_to_memory()
        {
            @$this->cache->store('key_10', 'value_10', 2, TRUE);
            @$this->cache->copy_entry_to_memory('key_10', TRUE);

            $this->assertEquals('value_10', @$this->cache->fetch('key_10'));
            $this->assertNull(@$this->cache->fetch('key_10', TRUE));
        }

        public function test_copy_all_entries_to_filesystem()
        {
            @$this->cache->store('key_11', 'value_11', 2);
            @$this->cache->store('key_12', 'value_12', 3);
            @$this->cache->store('key_13', 'value_13', 4);

            $this->cache->copy_all_entries_to_filesystem(TRUE);

            $this->assertEquals('value_11', @$this->cache->fetch('key_11', TRUE));
            $this->assertEquals('value_12', @$this->cache->fetch('key_12', TRUE));
            $this->assertEquals('value_13', @$this->cache->fetch('key_13', TRUE));

            $test_ttl = file_get_contents('key_11');
            $test_ttl = json_decode($test_ttl, TRUE);
            $this->assertEquals(time() + 2, $test_ttl['expiry']);

            $test_ttl = file_get_contents('key_12');
            $test_ttl = json_decode($test_ttl, TRUE);
            $this->assertEquals(time() + 3, $test_ttl['expiry']);

            $test_ttl = file_get_contents('key_13');
            $test_ttl = json_decode($test_ttl, TRUE);
            $this->assertEquals(time() + 4, $test_ttl['expiry']);

            $this->assertFalse(@$this->cache->fetch('key_11'));
            $this->assertFalse(@$this->cache->fetch('key_12'));
            $this->assertFalse(@$this->cache->fetch('key_13'));
        }

        public function test_copy_all_entries_to_memory()
        {
            $file_1 = json_encode(array('data' => 'test1', 'expiry' => time() + 1));
            $file_2 = json_encode(array('data' => 'test2', 'expiry' => time() + 2));

            file_put_contents('entry_1', $file_1);
            file_put_contents('entry_2', $file_2);

            @$this->cache->copy_all_entries_to_memory(TRUE);

            $this->assertEquals('test1', @$this->cache->fetch('entry_1'));
            $this->assertEquals('test2', @$this->cache->fetch('entry_2'));

            $this->assertNull(@$this->cache->fetch('entry_1', TRUE));
            $this->assertNull(@$this->cache->fetch('entry_2', TRUE));
        }

        public function test_find_most_stored_entry()
        {
            @$this->cache->store('entry_4', 'value_1');
            @$this->cache->store('entry_1', 'value_1');
            @$this->cache->store('entry_1', 'value_1');
            @$this->cache->store('entry_2', 'value_1');
            @$this->cache->store('entry_3', 'value_1');

            $this->assertEquals('entry_1', @$this->cache->find_most_stored_entry());
        }

        public function test_find_most_fetched_entry()
        {
            @$this->cache->store('entry_1', 'value_1');
            @$this->cache->store('entry_2', 'value_1');
            @$this->cache->store('entry_3', 'value_1');

            @$this->cache->fetch('entry_1', 'value_1');
            @$this->cache->fetch('entry_1', 'value_1');
            @$this->cache->fetch('entry_2', 'value_1');
            @$this->cache->fetch('entry_3', 'value_1');

            $this->assertEquals('entry_1', @$this->cache->find_most_fetched_entry());
        }
    }

?>