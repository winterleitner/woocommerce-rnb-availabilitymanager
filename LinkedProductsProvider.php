<?php

require_once __DIR__ . '/functions.php';

class LinkedProductsProvider
{
    private $linkedProducts;
    public function __construct($file)
    {
        $this->parse_linked_products($file);
    }

    function parse_linked_products($file)
    {

        // Read the JSON file contents
        $jsonContent = file_get_contents($file);

        // Decode the JSON data into a PHP array
        $this->linkedProducts = json_decode($jsonContent, true);

        // Check if the decoding was successful
        if ($this->linkedProducts === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "Failed to decode JSON. Error: " . json_last_error_msg();
            exit;
        }

        // Now $linkedProducts contains the array parsed from the JSON file
        print_r($this->linkedProducts);
    }

    /**
     * Get the inventory ids of linked products.
     * @param $product_id
     * @return array
     */
    public function get_linked_products($product_id)
    {
        foreach ($this->linkedProducts as $linkedProduct) {
            if (array_any($linkedProduct, function($value) use ($product_id) {return $value == $product_id;})) {
                return array_filter($linkedProduct, function($value) use ($product_id) {return $value != $product_id;} );
            }
        }
        return [];
    }

    public function get_product_id($inventory_id)
    {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'rnb_inventory_product';
            return $wpdb->get_var($wpdb->prepare("SELECT product FROM $table_name WHERE inventory = %d", $inventory_id));
        } catch (Exception $e) {
            return null;
        }
    }
}