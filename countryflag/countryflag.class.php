<?php
/*  Country Flag
 *  Plugin for Discuz! X3.5 Rev.1+
 *  Copyright (c) by Valery Votintsev, codersclub.org
 *    https://codersclub.org/discuzx/home.php?mod=space&uid=1
 *  Copyright (c) by jaideejung007, discuzthai.com
 *    https://discuzthai.com
 *  Original idea by KEN
 *    https://codersclub.org/discuzx/home.php?mod=space&uid=1563
 *  V1.1 UPDATE 20120328 by Ken
 *  V1.2 UPDATE 20120607 by vot
 *  V1.3 UPDATE 20121107 by vot
 *  V1.4 UPDATE 20130214 by vot
 *  V1.5 UPDATE 20130509 by vot
 *  V1.6 UPDATE 20130620 by vot
 *  V1.7 UPDATE 20130712 by vot
 *  V1.8 UPDATE 20130724 by vot
 *  V2.0 UPDATE 20230715 by jaideejung007
 *  V2.0.1 UPDATE 20230724 by jaideejung007
 */

if(!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

require_once libfile('function/misc');

class plugin_countryflag {

  function plugin_countryflag() {
    global $_G;

    $this->hideuid  = $_G['cache']['plugin']['countryflag']['hideuid'];
    $this->template = $_G['cache']['plugin']['countryflag']['template'];

  }

  function global_header() {
    global $_G;
    loadcache('plugin');
    $jdzConfig = $_G['cache']['plugin']['countryflag'];
    $return = '<link rel="stylesheet" href="'.$jdzConfig['flagicons_cdn_url'].'?'.VERHASH.'">';
    return $return;
  }
}

class plugin_countryflag_forum extends plugin_countryflag {

  function countryflag_go() {
    global $_G, $postlist;

    $return = array();

    if(empty($postlist) || !is_array($postlist)) return $return;

    if(!empty($this->hideuid)){
      $hideidarr = explode(',', $this->hideuid);
    }

    foreach ($postlist as $pid => $post) {
      if(!in_array($post['authorid'], $hideidarr)) {
        $useip = $post['useip'];

        $isoCode    = $this->jdz_geoip($useip, true, false, false);
        $cficon   = strtolower($isoCode);
        $country = $this->jdz_geoip($useip, false, false, true);
        $city = $this->jdz_geoip($useip, false, true, false);

        if($isoCode == 'LAN' || $isoCode == 'Localhost' || $isoCode == 'Invalid IP Address' || $isoCode == 'ERR' || $isoCode == '??'){
          $cficon = 'xx';
        }

        $flag_country = $country;
        $flag_city = $city;
        $flag_image   = '<span class="fi fi-'.$cficon.'" title="'.$city.', '.$flag_country.'"></span>';

        if($this->template){
          $str = $this->template;
          $str = preg_replace('/\$flag_image/i', $flag_image, $str);
          $str = preg_replace('/\$flag_country/i', $flag_country, $str);
          $str = preg_replace('/\$flag_city/i', $flag_city, $str);
          $return[] = $str;
        } else {
          $return[] = '<p style="white-space: nowrap; overflow: hidden;"><span class="fi fi-'.$cficon.'" title="'.$city.', '.$flag_country.'"></span>&nbsp;'.$city.',&nbsp;'.$country.'</p>';
        }
      } else {
        $return[] = '';
      }
    }
    return $return;
  }

  function viewthread_sidebottom_output() {
    return $this->countryflag_go();
  }

  // ฟังก์ชันแปลงไอพีเป็นชื่อประเทศ โดยจะวิเคราะห์/ตรวจสอบว่าไอพีถูกต้องหรือไม่ หากถูกต้องจะทำการแปลงไอพีเป็นชื่อประเทศ และถ้าไม่ถูกต้องจะส่งคืนค่าตามเงื่อนไขที่กำหนดไว้
  function jdz_geoip($ip, $isoCode = false, $city = false, $country = false) {
    /**
     * isoCode คือ รหัสประเทศ เช่น TH
     * city คือ ชื่อเมือง/นคร/เขต เช่น Ban Dan
     * country คือ ชื่อประเทศ เช่น Thailand
     */
    $return = '';
    // ตรวจสอบว่าเป็น IPv4 หรือ IPv6 หรือไม่ หากใช่ ให้ทำการแยกไอพี IPv4 เพื่อใช้สำหรับตรวจสอบไอพีในขั้นตอนต่อไป
    if ($this->isValidIP($ip)) {
      $iparray = (strpos($ip, ':') !== false) ? explode(':', $ip) : explode('.', $ip);
      // ตรวจสอบความถูกต้องของไอพีว่าเป็น LAN, Localhost หรือไม่
      if ($iparray[0] == 10 || ($iparray[0] == 192 && $iparray[1] == 168) || ($iparray[0] == 172 && ($iparray[1] >= 16 && $iparray[1] <= 31))) {
          $return = 'LAN';
      } elseif ($iparray[0] == 127) {
          $return = 'Localhost';
      } elseif (count($iparray) == 4 && ($iparray[0] > 255 || $iparray[1] > 255 || $iparray[2] > 255 || $iparray[3] > 255)) {
          $return = 'Invalid IP Address';
      } else {
          // หากไอพีถูกต้อง ให้เริ่มทำการแปลงไอพีเป็นชื่อประเทศ
          require_once constant("DISCUZ_ROOT").'./data/ipdata/geoip2.phar';
          $ipdatafile = constant("DISCUZ_ROOT").'./data/ipdata/GeoLite2-City.mmdb';
          $reader = new GeoIp2\Database\Reader($ipdatafile);
          try {
            $jdzrecord = $reader->city($ip);
            if($isoCode) {
              $return = $jdzrecord->country->isoCode; // รหัสประเทศ เช่น TH
            }
            if($city) {
              $return = $jdzrecord->city->name; // ชื่อเมือง/นคร/เขต เช่น Ban Dan
            }
            if($country) {
              $return = $jdzrecord->country->name; // ชื่อประเทศ เช่น Thailand
            }
          } catch (Exception $e) {
            $return = 'ERR';
          }
          if(!@$return) {
            $return = '??';
          }
      }
    } else {
      $return = 'Invalid IP Address';
    }
    return $return;
  }

  function isValidIP($ip) {
    // Check if it's a valid IPv6 address
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return true;
    }
  
    // Check if it's a valid IPv4 address
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return true;
    }
    return false;
  }  
}
