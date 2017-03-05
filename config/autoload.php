<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2016 Intelligent Spark
 *
 * @package Isotope Shipping Zones Advanced
 * @link       http://isotopeecommerce.org
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

if (class_exists('NamespaceClassLoader')) {
    /**
     * Register PSR-0 namespace
     */
    NamespaceClassLoader::add('IntelligentSpark', 'system/modules/isotope_shippingmethodextended/library');
}


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    'iso_checkout_shipping_method_extended'     => 'system/modules/isotope_shippingmethodextended/templates/checkout'
));