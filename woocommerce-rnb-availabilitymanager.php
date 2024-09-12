<?php

/*
Plugin Name: Woocommerce Rnb Availability Manager
Plugin URI: https://github.com/winterleitner/woocommerce-rnb-availabilitymanager
Description: A plugin extending Woocommerce Rental & Booking to allow one booked item to block others.
Version: 1.0
Author: Felix Winterleitner
Author URI: https://winterleitner.github.com
License: MIT
*/
require_once __DIR__ . '/LinkedProductsProvider.php';
$linked_products_source = __DIR__ . "/linked_products.json";

## When a new order item is created, add the linked item(s) blocked times
add_action('woocommerce_new_order_item', 'on_create', 50, 3);

## When order is updated, update the linked item
add_action('woocommerce_order_status_changed', 'on_update', 20, 3);

## When order is deleted, free up the linked item
add_action('trashed_post', 'on_delete', 30, 1);

function on_create($item_id, $item, $order_id)
{
    global $wpdb, $linked_products_source;

    $item_data = $item->get_data();
    if (!isset($item_data['product_id']) || empty($item_data['product_id'])) {
        return;
    }

    $product_id = $item_data['product_id'];
    $product_type = wc_get_product($product_id)->get_type();
    if ($product_type !== 'redq_rental' || !isset($item->legacy_values['rental_data'])) {
        return;
    }

    // Table name (adjust this to your actual table name)
    $table_name = $wpdb->prefix . 'rnb_availability';

    $rental_data = $item->legacy_values['rental_data'];
    if (empty($rental_data)) {
        return;
    }

    // Retrieve the record
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d AND block_by = 'FRONTEND_ORDER'", $order_id), ARRAY_A);

    if (!$record) {
        // no availability record found, nothing to do
        return;
    }

    $inventory_id = $rental_data['booking_inventory'];

    // get the linked products
    $linker = new LinkedProductsProvider($linked_products_source);
    $linked = $linker->get_linked_products($inventory_id);
    unset($record['id']);
    $record['block_by'] = 'CUSTOM';
    foreach ($linked as $p) {
        $record['inventory_id'] = $p;
        $record['product_id'] = $linker->get_product_id($p);
        $wpdb->insert($table_name, $record);
    }
}

function on_update($order_id, $old_status, $new_status)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'rnb_availability';
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d AND block_by = 'FRONTEND_ORDER'", $order_id), ARRAY_A);

    if (!$record) {
        // no availability record found, nothing to do
        return;
    }
    $where = [
        'order_id' => $order_id,
        'block_by' => 'CUSTOM',
    ];

    $updated_data = [
        'pickup_datetime' => $record['pickup_datetime'],
        'return_datetime' => $record['return_datetime'],
        'rental_duration' => $record['rental_duration'],
        'updated_at' => $record['updated_at'],
        'delete_status' => $record['delete_status']
    ];

    $wpdb->update($table_name, $updated_data, $where);
}

function on_delete($order_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'rnb_availability';
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d AND block_by = 'FRONTEND_ORDER'", $order_id), ARRAY_A);

    if (!$record) {
        // no availability record found, nothing to do
        return;
    }
    $where = [
        'order_id' => $order_id,
        'block_by' => 'CUSTOM',
    ];

    $updated_data = [
        'updated_at' => current_time('mysql'),
        'delete_status' => true
    ];

    $wpdb->update($table_name, $updated_data, $where);}

