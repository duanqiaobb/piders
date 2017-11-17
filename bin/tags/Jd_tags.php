<?php
/**
 * 分析京东商城的标签
 */
require_once(dirname(__FILE__).'/../../app.php');
use Model\UrltagModel;
use Extension\DBExtension;
use Util\StructHtml\SearchEntry;
use Controller\WebsiteController;
use Model\ProductModel;
use Util\Api;
$GLOBALS['website']['id'] = 1;
DBExtension::switch_db('phpspider');
function proxy_wrapper($callback) {
    \requests::$input_encoding='GBK';
    \requests::$output_encoding='UTF-8';
    \requests::set_useragents(
        array(
            'Mozilla/5.0 (Windows; U; Windows NT 5.2) Gecko/2008070208 Firefox/3.0.1',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; Trident/4.0)',
            'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.12) Gecko/20080219 Firefox/2.0.0.12 Navigator/9.0.0.6',
            'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Version/3.1 Safari/525.13',
            'Mozilla/5.0 (iPhone; U; CPU like Mac OS X) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/4A93 Safari/419.3',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
            'Mozilla/5.0 (Macintosh; PPC Mac OS X; U; en) Opera 8.0',)
        );
    $proxy_ip = Api::getIp();
    if ($proxy_ip) {
        requests::set_proxies(
            array("http"=>$proxy_ip,
            "https"=>$proxy_ip
        ));
        $callback();
    } else {
    
       printf("%s\n","Error: A unexpected error occurred when get the proxy ip");
    }
}
function detect_tag_type($tag_name) {
    $promtag_maps =  array(
        '京东秒杀',
        '京东闪购',
        '加价购',
        '满额返券',
        '满减',
        '多买优惠',
        '会员特价',
    );
    $franchise_maps = array(
        '京东超市',
        '京东自营'
    );
    $seller_maps = array(
        '99元免基础运费',
        '京准达',
        '211限时达',
        '京东配送'
    );
    $festival_maps = array(
        '中秋',
        '国庆'
    );
    $ftag_maps =  array(
        '京东精选',
    );
    if ( in_array($tag_name, $promtag_maps ) ){
        return '促销标签';
    } 
    $festival_feature='/.*('.implode('|',$festival_maps).').*/i';
    $flag = preg_match($festival_feature,$tag_name,$matches); 
    if ($flag !== false && !empty($matches[1])) {
        return '节日标签';
    } 
    if (in_array($tag_name, $franchise_maps) ){
        return '经营标签';
    } 
    if (in_array($tag_name,$seller_maps)) {
        return '配送标签';
    }
    return '特点标签';
   
}
function prune_tags() {
        $tag_model = new UrltagModel();
        printf("%s\n","Cleaning up the redisdual tag in database!");
        $clean_flush_flag = $tag_model->prune_by_website_id($GLOBALS['website']['id']);
        if (!$clean_flush_flag ) {
            printf("%s\n","Error when delete the redsidual data from database!");
            return false;
        }
        printf("%s\n","Clean up the redisdual tag in database done!");
    return true;
}

function pouring_product_details($product_details) {
    printf("%s\n","Data of product_details is pouring into database ...!");
    foreach($product_details as $id => $product_detail ) {
        $product = new ProductModel($id,$GLOBALS['website']['id']);
        if (!empty($product_detail)) {
            $product_detail['uid']= spawn_guid();
            $product_detail['time'] = spawn_guid();
            $product->table('wine_info')
                ->fields(['uid'=>'id','id'=>'out_product_id','name'=>'name_ch','pro_price'=>'current_price','url'=>'product_url','price'=>'market_price'])
                ->fromArray($product_detail)
                ->add();
        } else {
            continue;
        }
   }
    printf("%s\n","Data of product_details is pouring into database ... done!");
}

function pouring_product_tags($product_details) {
    printf("%s\n","Data of tags is pouring into database ...!");
    foreach($product_details as $product_detail) {
        if (!empty($product_detail['tags'])) {
            $tag_datas = $product_detail['tags'];
            $result = DBExtension::insert_batch('url_tag',$tag_datas);
            if ($result === false) {
                printf("%s\n","Error: Data of tags poured error!");
            }
        }
   }
      printf("%s\n","Data of tags is pouring into database ... done!");
}

function get_tags_from_name($name) {
    $tags = array();
    $size_regex = '/^.*(\d)[支|瓶].*/i';
    $ntag_regex = '/^.*【(.*)】.*/i';
    if (empty($name)) {
        return false;
    }
    //提取名庄，支数，直营
    if (strpos($name,'名庄') !== false) {
        $tags[] = '名庄';
    }
    preg_match($size_regex,$name,$match);
    if (!empty($match[1])) {
        $tags[] = $match[1].'支装';
    }
    preg_match($ntag_regex,$name,$match);
    if (!empty($match[1])) {
        $tags[] = $match[1];
    }
    return $tags;
}

function get_tags_from_price($price){
    if ($price > 1000) {
        return '奢侈美酒';
    }
}

function get_coupon_tags ($api_content,&$tags) {
    if (empty($api_content['skuCoupon'])) {
        return false;
    }
    $coupon_tags = $api_content['skuCoupon'];
    if (!empty($coupon_tags)) {
        foreach ($coupon_tags as $coupon_tag) {
            $type = @$coupon_tag['couponType'];
            switch($type) {
            case 1 :
                $quota=@$coupon_tag['quota'];
                $discount = @$coupon_tag['discount'];
                $name = '满'.$quota.'减'.$discount;
                $tags[] = $name;
                break;
            default:
                break;
            }
    }
    }
    
}

function get_price($product_id) {
    $api_url = "https://p.3.cn/prices/mgets?type=1&skuIds=J_";
    $api_url = $api_url.$product_id;
    $result = null;
    printf("%s\n","Collecting price from api ... ");
    proxy_wrapper(function() use (&$result,$api_url) {
        $result = requests::get($api_url);
    });
    $try_times = 0;
    $max_retry = 10;
    while(empty($result) && $try_times < $max_retry ) {
        printf("%s\n","Collecting price from api ... failed, retry ".$try_times."/".$max_retry);
        proxy_wrapper(function() use (&$result, $api_url){
        $result = requests::get($api_url);
        });
        $try_times++;
    }
    if (empty($result)) {
        return false;
    }
    printf("%s\n","Collection price from api ... done");
    return json_decode($result,true);
}

function get_tags_from_api($product_id) {
    $tags = array();
    $api_url = "https://cd.jd.com/promotion/v2?skuId=".$product_id."&area=19_1607_3638_0&cat=12259%2C12260%2C9438";
    $result = null;
    printf("%s\n","Collecting tags from api ... ");
    proxy_wrapper(function() use (&$result, $api_url){
        $result = requests::get($api_url);
    });
    $try_times = 0;
    $max_retry = 10;
    while(empty($result) && $try_times < $max_retry ) {
        printf("%s\n","Collecting tags from api ... failed, retry ".$try_times."/".$max_retry);
        proxy_wrapper(function() use (&$result, $api_url){
        $result = requests::get($api_url);
        });
        $try_times++;
    }
    if (empty($result)) {
        return false;
    }
    $raw_info = json_decode($result,true);
    if (!empty($raw_info['quan']['title'])) {
        $tags[] = array('tag_desc'=>'满额返券','tag_info'=>$raw_info['quan']['title']);
    }
    //Get the tags inside the pink bar which under product name
    if (!empty($raw_info['ads'])) {
        $ads_tags = $raw_info['ads'];
        foreach( $ads_tags as $tag) {
            if (!empty($tag['ad'])) {
                $tag['ad'] = strip_tags($tag['ad']);
                $tag['ad'] = str_replace(array('】','【'),' ',$tag['ad']);
                //trip the empty elements
                $tmp_tags = array_filter(preg_split('/[\s\t,;]+/',$tag['ad']),function($value){
                    if (empty($value)) {
                        return false;
                    }
                    return true;
                });
                $tags = count($tmp_tags) > 0 ? array_merge($tmp_tags,$tags):$tags;
                unset($tmp_tags);
            }
        }
        printf("%s\n","Collecting tags from api ... done");
    }
    //Get the tags inside promotion box
    if (!empty($raw_info['prom']['tags'])) {
        $tmp2_tags = $raw_info['prom']['tags'];
        foreach($tmp2_tags as $tmp_tag) {
            $name = @$tmp_tag['name'];
            $content= @$tmp_tag['content'];
            if (empty($name)) {
                continue;
            }
            if (empty($content)) {
                $tags[] = $name;
            } else {
                $tmp2_tag['tag_info'] = $content;
                $tmp2_tag['tag_desc'] = $name; 
                $tags[] = $tmp2_tag;
            }
        }

        if (!empty($raw_info['prom']['pickOneTag'])) {
            $tmp3_tags =  $raw_info['prom']['pickOneTag'];
            foreach($tmp3_tags as $tmp_tag) {
                $name = @$tmp_tag['name'];
                $content= @$tmp_tag['content'];
                if (empty($name)) {
                    continue;
                }
                if (empty($content)) {
                    $tags[] = $name;
                } else {
                    $tmp3_tag['tag_info'] = $content;
                    $tmp3_tag['tag_desc'] = $name; 
                    $tags[] = $tmp3_tag;
                }
            }
        }
    }
    //Get the coupons tags
    get_coupon_tags($raw_info,$tags);
    if (empty($tags)) {
        return false;
    }
    return $tags;
}

function parse_details_html($html){
    $product_details = array();
    $product_details['name'] = \selector::select($html,'//div[contains(@class,"itemInfo-wrap")]/div[contains(@class,"sku-name")]/text()[string-length(normalize-space(.)) > 0 ]');
    $product_details['name'] = trim($product_details['name']);
    //获取商品名旁边的标签
    $side_tag_name = \selector::select($html,'//div[contains(@class,"itemInfo-wrap")]/div[contains(@class,"sku-name")]/img/@alt');
    //获取商品顶部的推广标签
    $top_tag_name = \selector::select($html,'//div[contains(@class,"itemInfo-wrap")]/div/div/strong');
    //Get the tag in a pink box under product name 
    
    if (!empty($top_tag_name)) {
        $product_details['tags'][] = $top_tag_name;
    }
    if (!empty($side_tag_name)) {
        $product_details['tags'][] = $side_tag_name;
    }
    if(empty($product_details)) {
        return false;
    }
    return $product_details;
}


function get_actproduct_details($products) {
    if (!is_array($products)) {
        throw new ErrorException("Argument Error: argument must be a array");
    }
    if (empty($products)) {
        printf('%s\n',"No products are provided");
        return false;
    }
    $count = 0;
    $product_it = new ArrayIterator($products);
    $max_retry = 10;
    $retry_times = 0;

    printf("%s\n",'Collecting product details ... 0/'.count($products));
    while($product_it->valid()) {
        $product = $product_it->current();
        $url= @$product['url'];
        \requests::$input_encoding='GBK';
        \requests::$output_encoding='UTF-8';
        \requests::set_useragents(
            array(
                'Mozilla/5.0 (Windows; U; Windows NT 5.2) Gecko/2008070208 Firefox/3.0.1',
                'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; Trident/4.0)',
                'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13',
                'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.12) Gecko/20080219 Firefox/2.0.0.12 Navigator/9.0.0.6',
                'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Version/3.1 Safari/525.13',
                'Mozilla/5.0 (iPhone; U; CPU like Mac OS X) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/4A93 Safari/419.3',
                'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
                'Mozilla/5.0 (Macintosh; PPC Mac OS X; U; en) Opera 8.0',)
        );
        $proxy_ip = Api::getIp();
        if ($proxy_ip) {
            requests::set_proxies(
                array("http"=>$proxy_ip,
                    "https"=>$proxy_ip
                ));
        } else {
            printf("%s\n","Error: A unexpected error occurred when get the proxy ip");
        }
        $product_details_html = \requests::get($url);
        if(empty($product_details_html)) {
            printf("%s\n","Get product details page failed ! retrying ".$retry_times.'/'.$max_retry);
            if ($retry_times > $max_retry) {
                $product_it->next();
                $retry_times = 0;
            }  else {
                $retry_times++;
            }
            $failed_produts[] = $products;
            continue;
        }
        //get the base product_info
        $product_details = parse_details_html($product_details_html);
        //get the extra tags
        $tags  = get_tags_from_api($product['id']);
        if (!empty($tags) && !empty($product_details['tags'])) {
            $product_details['tags'] = array_merge($product_details['tags'],$tags);        } else if (!empty($tags) && empty($product_details['tags'])) {
            $product_details['tags'] = $tags;
        }
        //get the price
        $prices = get_price($product['id']);
        $prices= @$prices[0];

        if (empty($prices)) {
            printf("%s\n","Can't get price info for".$product['id']);
        } 
        $tags_from_name = get_tags_from_name($product_details['name']);
        if (!empty($tags_from_name) && !empty($product_details['tags'])) {
            $product_details['tags'] = array_merge($product_details['tags'],$tags_from_name);        
        } else if (!empty($tags) && empty($product_details['tags'])) {
            $product_details['tags'] = $tags_from_name;
        }
        $tags_from_price = get_tags_from_price($product_details['price']);
        if (!empty($tags_from_price) && !empty($product_details['tags'])) {
            $product_details['tags'] = array_merge($product_details['tags'],$tags_from_price);        
        } else if (!empty($tags) && empty($product_details['tags'])) {
            $product_details['tags'] = $tags_from_price;
        }

        $product['name'] = $product_details['name'];
        $product['price'] = $prices['op'];
        $product['pro_price'] = $prices['p'];
        if(!empty($product_details['tags'])) {
            foreach($product_details['tags'] as &$tag) {
                $tmp_tag = array();
                if (is_array($tag)) {
                    $tmp_tag['tag_desc'] = @$tag['tag_desc'];
                    $tmp_tag['tag_info'] = @$tag['tag_info'];
                } else {
                    $tmp_tag['tag_desc'] = $tag;
                    $tmp_tag['tag_info'] = '';
                }
                $tmp_tag['ah_id'] = $product['ah_id'];
                $tmp_tag['ctime'] = date('Y-m-d h:i:s');
                $tmp_tag['uid'] = spawn_guid();
                $tmp_tag['type_name'] =detect_tag_type($tmp_tag['tag_desc']);
                $tag = $tmp_tag;
            }
        }
        if (!empty($product_details['tags'])) {
            $product['tags'] = $product_details['tags'];
        }
        $products[$product['id']] = $product;
        $product_it->next();
        $count++;
        printf("%s\n",'Collecting product details ... '.$count.'/'.count($products));
    }
//    var_dump($products);
    return $products;
}


function retrieve_product_ids ($product_ids) {
    $products = array();
    foreach($product_ids as $id) {
        $products[$id]['id'] = $id;
        $products[$id]['url'] = 'http://item.jd.com/'.$id.'.html';
    }
    return $products;
}
function  Jd_flash_tag() {
    $getExtras = function($searchentry)  {
        $current_page = $searchentry->get_current_page();
        if ($current_page != -1) {
            $api_url = "https://search.jd.com/s_new.php?keyword=".$searchentry->keyword."&enc=utf-8&qrst=1&rt=1&stop=1&vt=2&suggest=1.his.0.0&page=".($current_page+1)."&s=29&scrolling=y&tpl=1_M";
            //var_dump("Api URL:");
            //var_dump($api_url);
            \requests::set_referer($searchentry->entry);
            \requests::$input_encoding="UTF-8";
            \requests::$output_encoding = "UTF-8";
            $api_result = \requests::get($api_url);
            $searchentry->extern(\selector::select($api_result,'//li/div/div/a/@href'));

        }
    };
    
    $time_start = time();
    $searcher = new SearchEntry("https://search.jd.com/Search");
    $search_urls = $searcher->keyword_param('keyword')->extra_param('enc=utf-8')->totalpages('//div[@id=\'J_topPage\']/span/i')->search('葡萄酒','//div[@id="J_goodsList"]/ul/li/div/div/a/@href')->iterate($getExtras)->reset_totalpages(2,'*')->skip('even')->go();
    /*
    $search_urls = array(
        'http://item.jd.com/16299250454.html',
        'http://item.jd.com/10124414717.html',
        'http://item.jd.com/10189569472.html'
    );
<<<<<<< HEAD
     */
    $website = new WebsiteController($GLOBALS['website']['id']);
    $website->suffix_product_url('.html');
    $website->prefix_product_url('http://item.jd.com/');
    //https://item.jd.com/12098230917.html
    $url_format  = '/\/\/item\.jd\.com\/(\d+)\.html/i';
    $urls = $website->format_urls($search_urls,$url_format,function($url){
                if (strpos($url,'http') !== false){
                    return $url;
                }
                return 'https:'.$url;
    });
    $product_ids = $website->parse_product_id($urls,'/https?:\/\/item\.jd\.com\/(\d+)\.html/i');
    if (empty($product_ids)) {
        printf("%s\n","Error: Can't get product ids");
        return false;
    }
    $products = retrieve_product_ids($product_ids);
    if (!empty($product_ids)) {
        printf("%s\n","Syncing html...");
        $assoc_arrs = $website->sync_html($product_ids);
        printf("%s\n","Syncing html... done");
        if ($assoc_arrs) {
            $counter = 0;
            foreach($assoc_arrs as $product_id => $all_html_id) {
                $tag_data['uid'] = spawn_guid();
                $tag_data['ah_id'] = $all_html_id;
                $products[$product_id]['ah_id']= $all_html_id;
                $tag_datas[] = $tag_data;
                $counter++;
                }
            }
    }
    //获取商品详情
    $product_details = get_actproduct_details($products); 
    //Store the product details
    pouring_product_details($product_details);
    //Store the product tags
    prune_tags();
    pouring_product_tags($product_details);
    //更新商品详情
    //  $details_result = $website->updateDetails($product_details);
    //更新商品URL
    //    $url_results = $website->updateUrls($product_urls);
   // if ($result === false) {
   //     printf("%s\n","Error:Database error!");
   //     exit(0);
   // }
    $time_end = time();
    var_dump($product_details);
    echo "Time cost: ".round(($time_end - $time_start)/3600, 3)."\n";
}

if (PHP_SAPI != 'cli') {
    printf("This script must be run under cli!");
    exit(0);
} else {
    Jd_flash_tag();
}