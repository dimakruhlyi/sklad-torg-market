<?php

/*--------------------------------------------------------------------------/
* @Author		KulerThemes.com http://www.kulerthemes.com
* @Copyright	Copyright (C) 2012 - 2013 KulerThemes.com. All rights reserved.
* @License		KulerThemes.com Proprietary License
/---------------------------------------------------------------------------*/

class ControllerModuleKulerslides extends Controller {
	protected function index($setting) {
		static $module = 0;

        $setting['module_title'] = $this->translate($setting['module_title'], $this->config->get('config_language_id'));

        $this->language->load('module/kuler_slides');

        $this->document->addScript('catalog/view/javascript/jquery/jquery.jcarousel.min.js');

        if (file_exists('catalog/view/theme/' . $this->config->get('config_template') . '/stylesheet/carousel.css')) {
            $this->document->addStyle('catalog/view/theme/' . $this->config->get('config_template') . '/stylesheet/carousel.css');
        } else {
            $this->document->addStyle('catalog/view/theme/default/stylesheet/carousel.css');
        }

		$this->data['button_wishlist'] = $this->language->get('button_wishlist');
		$this->data['button_compare'] = $this->language->get('button_compare');
		$this->data['button_cart'] = $this->language->get('button_cart');

		$this->load->model('catalog/product');
		$this->load->model('tool/image');


		$this->data['module_title'] = $setting['module_title'];
        $this->data['show_title'] = $setting['show_title'];
		$this->data['setting'] = $setting;
		$this->data['products'] = array();
		
        $tmp = isset($setting['tabs'][1]) ? array_merge_recursive($setting, $setting['tabs'][1]) : $setting; unset($tmp['tabs']);

        $this->data['setting'] = $tmp;
        
        $products = array();
		
		if (isset($setting['tabs']) && is_array($setting['tabs'])) {
			$tabs = $setting['tabs'];
			foreach($tabs as $k => $tab) {
				$results = array();
				$options = array(
					'limit'             => $tab['limit'],
					'width'             => $tab['image_width'],
					'height'            => $tab['image_height'],
					'description_limit' => !empty($tab['description_limit']) ? intval($tab['description_limit']) : 100
				);
				
				// Get current tab products by tab type
				if(isset($tab['tab_type']) && $tab['tab_type'] == 'category') {
					$options['category_id'] = $tab['category_id'];
					$results = $this->getProductsCategory($options);
				} else if(isset($tab['tab_type']) && $tab['tab_type'] == 'product') {
					$options['products'] = $tab['products'];
					$results = $this->getProductsCustomize($options);
				} else if(isset($tab['tab_type']) && $tab['tab_type'] == 'bestseller') {
					$results = $this->getProductsBestseller($options);
				} else if(isset($tab['tab_type']) && $tab['tab_type'] == 'latest') {
					$results = $this->getProductsLastest($options);
				} else if(isset($tab['tab_type']) && $tab['tab_type'] == 'special') {
					$results = $this->getProductsSpecial($options);
                } else {
					continue;
				}
				
                $products = $results;
			}
		} else {
			return false;
		}

        foreach ($products as $product_index => $product)
        {
            $product_categories = $this->model_catalog_product->getCategories($product['product_id']);
            $first_category_id = !empty($product_categories) ? $product_categories[0]['category_id'] : 0;

            $products[$product_index]['href'] = $this->url->link('product/product', 'path=' . $this->getRecursivePath($first_category_id) . '&product_id=' . $product['product_id']);
        }
		
		// Split products to slide
		if($this->data['setting']['item']) {
			$products = array_chunk($products, $this->data['setting']['item']);
		}
		
		$this->data['products'] = $products;
		$this->data['module'] = $module++;

		$this->document->addStyle('catalog/view/kulercore/css/kuler_slides.css');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/module/kuler_slides.phtml')) {
			$this->template = $this->config->get('config_template') . '/template/module/kuler_slides.phtml';
		} else {
			$this->template = 'default/template/module/kuler_slides.phtml';
		}
		
		$this->render();
	}
	
	/**
	 * Get category products
	 * 
	 * @param array $option
	 *	category_id
	 *	limit
	 *	width
	 *	height
	 * 
	 * @return array List products
	 */
	protected function getProductsCategory($option) {
		$data = array(
			'filter_category_id' => $option['category_id'],
			'filter_filter'      => '', 
			'sort'               => 'p.sort_order',
			'order'              => 'ASC',
			'start'              => 0,
			'limit'              => isset($option['limit']) ?  $option['limit'] : 5
		);
		
		$products = array();

		$results = $this->model_catalog_product->getProducts($data);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $option['width'], $option['height']);
			} else {
				$image = false;
			}

			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$price = false;
			}

			if ((float)$result['special']) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$special = false;
			}	

			if ($this->config->get('config_tax')) {
				$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price']);
			} else {
				$tax = false;
			}				

			if ($this->config->get('config_review_status')) {
				$rating = (int)$result['rating'];
			} else {
				$rating = false;
			}

			$products[] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'image'       => $result['image'],
				'name'        => $result['name'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $option['description_limit']) . '..',
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'rating'      => $result['rating'],
				'reviews'     => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
				'href'        => $this->url->link('product/product', '&product_id=' . $result['product_id'])
			);
		}
		
		return $products;
	}
	
	protected function getProductsCustomize($option) {
		$products = array();
		$productArray = explode(',', $option['products']);
		$productArray = array_slice($productArray, 0, (int)$option['limit']);
		
		foreach ($productArray as $product_id) {
			$result = $this->model_catalog_product->getProduct($product_id);

			if ($result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $option['width'], $option['height']);
				} else {
					$image = false;
				}

				if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
				} else {
					$special = false;
				}	

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price']);
				} else {
					$tax = false;
				}				

				if ($this->config->get('config_review_status')) {
					$rating = (int)$result['rating'];
				} else {
					$rating = false;
				}

				$products[] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'image'       => $result['image'],
					'name'        => $result['name'],
					'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $option['description_limit']) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $result['rating'],
					'reviews'     => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
					'href'        => $this->url->link('product/product', '&product_id=' . $result['product_id'])
				);
			}
		}
		
		return $products;
	}
	
	protected function getProductsBestseller($option) {
		$products = array();
		$results = $this->model_catalog_product->getBestSellerProducts($option['limit']);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $option['width'], $option['height']);
			} else {
				$image = false;
			}

			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$price = false;
			}

			if ((float)$result['special']) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$special = false;
			}	

			if ($this->config->get('config_review_status')) {
				$rating = $result['rating'];
			} else {
				$rating = false;
			}

			$products[] = array(
				'product_id' => $result['product_id'],
				'thumb'   	 => $image,
				'image'      => $result['image'],
				'name'    	 => $result['name'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $option['description_limit']) . '..',
				'price'   	 => $price,
				'special' 	 => $special,
				'rating'     => $rating,
				'reviews'    => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
				'href'    	 => $this->url->link('product/product', 'product_id=' . $result['product_id']),
			);
		}
		
		return $products;
	}
	
	protected function getProductsLastest($option) {
		$data = array(
			'sort'  => 'p.date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => isset($option['limit']) ?  $option['limit'] : 5
		);
		
		$products = array();

		$results = $this->model_catalog_product->getProducts($data);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $option['width'], $option['height']);
			} else {
				$image = false;
			}

			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$price = false;
			}

			if ((float)$result['special']) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$special = false;
			}

			if ($this->config->get('config_review_status')) {
				$rating = $result['rating'];
			} else {
				$rating = false;
			}

			$products[] = array(
				'product_id' => $result['product_id'],
				'thumb'   	 => $image,
				'image'      => $result['image'],
				'name'    	 => $result['name'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $option['description_limit']) . '..',
				'price'   	 => $price,
				'special' 	 => $special,
				'rating'     => $rating,
				'reviews'    => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
				'href'    	 => $this->url->link('product/product', 'product_id=' . $result['product_id']),
			);
		}
		
		return $products;
	}
	
	protected function getProductsSpecial($option) {
		$data = array(
			'sort'  => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => isset($option['limit']) ?  $option['limit'] : 5
		);
		
		$products = array();

		$results = $this->model_catalog_product->getProductSpecials($data);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $option['width'], $option['height']);
			} else {
				$image = false;
			}

			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$price = false;
			}

			if ((float)$result['special']) { 
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
			} else {
				$special = false;
			}

			if ($this->config->get('config_review_status')) {
				$rating = $result['rating'];
			} else {
				$rating = false;
			}

			$products[] = array(
				'product_id' => $result['product_id'],
				'thumb'   	 => $image,
				'image'      => $result['image'],
				'name'    	 => $result['name'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $option['description_limit']) . '..',
				'price'   	 => $price,
				'special' 	 => $special,
				'rating'     => $rating,
				'reviews'    => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
				'href'    	 => $this->url->link('product/product', 'product_id=' . $result['product_id'])
			);
		}
		
		return $products;
	}

	public function loadChromeTemplate(array $setting, array $product)
	{
		static $template;

		if (!$template)
		{
			$template = new Template();
			$template->data = array(
				'button_cart'       => $this->language->get('button_cart'),
				'button_wishlist'   => $this->language->get('button_wishlist'),
				'button_compare'    => $this->language->get('button_compare')
			);
		}

		$template->data['setting'] = $setting;
		$template->data['product'] = $product;

		return $template->fetch($this->config->get('config_template') . '/includes/module_chrome_grid.tpl');
	}

    protected function getRecursivePath($category_id)
    {
        static $categories;

        if (empty($categories))
        {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");
            $raw_categories = $query->rows;

            $categories = array();
            foreach ($raw_categories as $raw_category)
            {
                $categories[$raw_category['category_id']] = $raw_category['parent_id'];
            }
        }

        if (!isset($categories[$category_id]))
        {
            return '';
        }

        $path = $category_id;
        $parent_id = $categories[$category_id];

        while (true)
        {
            if (!$parent_id)
            {
                break;
            }

            $path = $parent_id . '_' . $path;
            $parent_id = $categories[$parent_id];
        }

        return $path;
    }

    private function translate($texts, $language_id)
    {
        if (is_array($texts))
        {
            $first = current($texts);

            if (is_string($first))
            {
                $texts = empty($texts[$language_id]) ? $first : $texts[$language_id];
            }
            else if (is_array($texts))
            {
                if (!isset($texts[$language_id]))
                {
                    $texts[$language_id] = array();
                }

                foreach ($first as $key => $value)
                {
                    if (empty($texts[$language_id][$key]))
                    {
                        $texts[$language_id][$key] = $value;
                    }
                }
            }
        }

        return $texts;
    }
}
?>