<?php
if ( !defined('BASEPATH') )
    exit('No direct script access allowed');

class keyword_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * get listing keywords
     * 
     * @param int $offset
     * @param int  $limit
     * @return array
     */
    function get_keywords($offset = 0, $limit=25)
    {
        $sql = "
        SELECT SQL_CALC_FOUND_ROWS * FROM tweet_keywords 
        ORDER BY keyword
        LIMIT $offset,$limit ";
        $keywords['data'] = $this->db->query($sql)->result();
        $total = $this->db->query('SELECT FOUND_ROWS() as total')->row();
        $keywords['total'] = $total->total;
        return $keywords;
    }

    /**
     * get keyword based on id
     * 
     * @param int $keyword_id
     * @return obj 
     */
    function get_keyword($keyword_id)
    {
        return $this->db->get_where('tweet_keywords', array('id' => $keyword_id))->row();
    }

    function search_keyword($param=array())
    {
        $def['keyword'] = '';
        $def['limit'] = 10;
        $def['offset'] = 0;
        $def['start'] = date('Y-m-d');
        $def['end'] = date('Y-m-') . '31';

        $param = $param + $def;
        $start = $param['start'];
        $end = $param['end'];

        $sql = "
        SELECT *
        FROM tweets 
        WHERE tweet_text  LIKE '%" . $this->db->escape_like_str($param['keyword']) . "%'
                AND created_at BETWEEN ? AND ?
        ORDER BY created_at DESC
        ";
        $keywords['data'] = $this->db->query($sql, array($start, $end))->result();
        $keywords['total'] = count($keywords['data']);
        return $keywords;
    }

    function search_keyword_by_user_id($keyword, $user_id, $start, $end)
    {

        $sql = "
        SELECT *
        FROM tweets 
        WHERE tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
                AND user_id = ?
                AND created_at BETWEEN ? AND ?
        ORDER BY created_at DESC
        ";
        $keywords = $this->db->query($sql, array($user_id, $start, $end))->result();
        return $keywords;
    }

    function search_keyword_by_date($keyword, $date)
    {

        $sql = "
        SELECT *
        FROM tweets 
        WHERE tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
                AND DATE(created_at) = ?
        ORDER BY created_at DESC
        ";
        $keywords = $this->db->query($sql, array($date))->result();
        return $keywords;
    }

    function get_statistic($keyword, $start, $end)
    {
        $sql = "
        SELECT 
        count(*) as total_tweets, 
        COUNT(DISTINCT screen_name) AS total_accounts,
        DATE_FORMAT(created_at,'%Y-%m-%d')  as tanggal
        FROM `tweets` 
        WHERE 
            `tweet_text` LIKE '%" . $this->db->escape_like_str($keyword) . "%'
            AND created_at BETWEEN ? AND ?
        GROUP BY tanggal            
        ";

        $stats = $this->db->query($sql, array($start, $end))->result();
        $d['tweets'] = array();
        $d['acc'] = array();
        $d['tanggal'] = array();
        foreach ($stats as $r) {
            $d['tweet'][] = $r->total_tweets;
            $d['acc'][] = $r->total_accounts;
            $d['tanggal'][] = $r->tanggal;
        }
        return $d;
    }

    function get_cloud($keyword, $start, $end)
    {
        $sql = "
        SELECT 
        tweet_text
        FROM `tweets` 
        
        WHERE 
                tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
                AND created_at BETWEEN ? AND ?
        ";

        $stats = $this->db->query($sql, array($start, $end))->result();
        $str = '';
        foreach ($stats as $r) {
            $str .= " " . $r->tweet_text;
        }
        $words = $this->process_words($str);

        return array_reverse($words);
    }

    function process_words($text, $forbidden=array(), $min_length=4)
    {
        $index = array();
        $forbidden = array('yang', 'kepada', 'http', 'cont', 'dengan', 'oleh',
            'kita', 'kamu', 'saya', 'tapi', 'this', 'that', 'lockerz'
        );
        $text = str_replace('RT', ' rt ', $text);
        $text = strtolower($text);
        $text = str_replace('tidak ', 'tidak-', $text);
        $tandabaca = array('.', ',', '!', "\r\n", "\n", "\r", '$', '%', '&',
            '^', ')', '(', '+', '|', ':', ';', '/', '?', '=', "\""
        );
        $text = str_replace($tandabaca, ' ', $text);
        $text = str_replace($forbidden, ' ', $text);

        $text = str_replace('@', ' @', $text);
        $text = str_replace('#', ' #', $text);

        $text = strip_tags($text);

        $text = explode(' ', $text);
        natcasesort($text);
        $i = 0;
        foreach ($text as $word) {
            $string = trim($word);
            if ( (!empty($string)) && ($string != '') && (strlen($string) >= $min_length) ) {
                if ( !isset($index[$i]['word']) ) { // if not set this is a new index
                    $index[$i]['word'] = $string;
                    $index[$i]['count'] = 1;
                } elseif ( $index[$i]['word'] == $string ) {  // count repeats
                    $index[$i]['count'] += 1;
                } else { // else this is a different word, increment $i and create an entry
                    $i++;
                    $index[$i]['word'] = $string;
                    $index[$i]['count'] = 1;
                }
            }
        }

        function cmp($a, $b)
        {
            return ($a['count'] > $b['count']) ? +1 : -1;
        }

        if ( $index ) {

            usort($index, "cmp");
            return($index);
        } else {
            return $index;
        }
    }

    function get_dropdown_keyword()
    {
        $dd = array();
        $row = $this->db->get('tweet_keywords')->result();
        foreach ($row as $r) {
            $dd[$r->keyword] = $r->keyword;
        }
        return $dd;
    }

    function count_user($keyword, $start, $end)
    {
        $sql = "
            SELECT user_id, screen_name, count( `tweet_id` ) AS counted,
                '$start' as dstart,
                '$end' as dend
            FROM tweets
            WHERE 
                tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
                AND created_at BETWEEN ? AND ?
            GROUP BY `user_id`
            ORDER BY counted DESC
            LIMIT 30          
        ";
        $most_tweets = $this->db->query($sql, array($start, $end))->result();

        $sql = "
            SELECT 
                user_id, screen_name, max( `followers_count` ) AS counted,
                '$start' as dstart,
                '$end' as dend
            FROM tweets
            WHERE 
                tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
                AND created_at BETWEEN ? AND ?
            GROUP BY `user_id`
            ORDER BY counted DESC
            LIMIT 30          
        ";
        $most_followers = $this->db->query($sql, array($start, $end))->result();
        $d = array();
        for ($i = 0; $i < 30; $i++) {
            $data = array();
            if ( isset($most_tweets[$i]) ) {
                $data['t_screen_name'] = $most_tweets[$i]->screen_name;
                $data['t_counted'] = $most_tweets[$i]->counted;
                $data['t_user_id'] = $most_tweets[$i]->user_id;
                $data['t_start'] = $most_tweets[$i]->dstart;
                $data['t_end'] = $most_tweets[$i]->dend;
            } else {
                $data['t_screen_name'] = '&nbsp;';
                $data['t_counted'] = '&nbsp;';
                $data['t_user_id'] = false;
                $data['t_start'] = '';
                $data['t_end'] = '';
            }
            if ( isset($most_followers[$i]) ) {
                $data['f_screen_name'] = $most_followers[$i]->screen_name;
                $data['f_counted'] = $most_followers[$i]->counted;
                $data['f_user_id'] = $most_followers[$i]->user_id;
                $data['f_start'] = $most_followers[$i]->dstart;
                $data['f_end'] = $most_followers[$i]->dend;
            } else {
                $data['f_screen_name'] = '&nbsp;';
                $data['f_counted'] = '&nbsp;';
                $data['f_user_id'] = false;
                $data['f_start'] = '';
                $data['f_end'] = '';
            }
            $d[$i] = $data;
        }
        return $d;
    }

    function count_tweet($keyword, $start, $end)
    {
        $sql = "
        SELECT 
            sum(followers_count) as followers,
            count(DISTINCT user_id) as users ,
            count(tweet_id) as tweets,
            '$start' as dstart,'$end' as dend,'$keyword' as dkeyword
        FROM tweets 
        WHERE tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
            AND created_at BETWEEN ? AND ?
        ";

        $r = $this->db->query($sql, array($start, $end))->row();
        return $r;
    }

    function get_freq($keyword, $start, $end)
    {
        $sql = "
        SELECT 
            count(tweet_id) as tweets,
            sum(followers_count) as impression,
            count(distinct user_id) as users,
            date(`created_at`) as tanggal
        FROM tweets
        WHERE tweet_text  LIKE '%" . $this->db->escape_like_str($keyword) . "%'
            AND created_at BETWEEN ? AND ?
        GROUP BY tanggal
        ORDER BY tanggal DESC            
        ";
        $r = $this->db->query($sql, array($start, $end))->result();
        return $r;
    }
    
    function get_user($user_id){
        $this->db->where('user_id',$user_id);
        return $this->db->get('tweet_users')->row();
    }

}