<?php
require_once(WPWIKI_FILE_PATH.'/lib/class_wikiparser.php');

/* FUGGGGGGGGG */
global $interWiki;

/*CWJ*/ $interWiki = array(
'w' => 'wikipedia:',
'wp' => 'wordpress:',
'codex' => 'wordpress:',
'wordpress' => array(
		'q' => 'http://codex.wordpress.org/$1',
	),
'wikipedia' => array(
		'q' => 'http://en.wikipedia.org/$1',
	),
'php' => array(
		'q' => 'http://php.net/$0',
	),
);

class WPW_WikiParser extends WikiParser {
	// 2011-01-02 arjen adjusted to deal with namespaces
    function wiki_link($topic,$namespace='') {
	    global $wpdb;
	    global $post;
	    global $interWiki;
	    
    
	    $slug = sanitize_title($topic);
	    
	    $wiki = $wpdb->get_var('SELECT `p`.`id` FROM `' . $wpdb->posts . '` `p` WHERE `p`.`post_type` = "wiki" AND `p`.`post_name` = "'.$slug.'"');
	
	    if (is_object($post) and $post->post_name==$slug) :

	    endif;
	    
	    if (!$wiki)
	    	return 'new?redlink=1&title='.($namespace ? $namespace.':' : '').$topic;
	    else
			return ($namespace ? strtolower(preg_replace('/[ -]+/', '-', $namespace)).'/' : '') . strtolower(preg_replace('/[ -]+/', '-', $topic));
    }
	
    function handle_escapedlink($matches) {
    	    return $matches[2];
    }
    
    public function toWikiHref ($href) {
    	    /* CWJ: Code mode . . . */
    	    $href = stripslashes($href);
    	    
    	    $href = preg_replace("\007(->)\007", "_", $href);
    	    return trim(
    	    	    preg_replace('/[^a-zA-Z_0-9\-\s().:]/', '', $href)
	    );
    }
    
	// 2011-01-02 arjen adjusted to deal with namespaces
	function handle_internallink($matches) {
    
		global $wpdb, $post, $interWiki;

		$nolink = false;

		$titleSplit = preg_split('/(?<!\\\)\|/', $matches[2], 2);
		$suffix = (isset($matches[3]) ? $matches[3] : '');

		// Default case: no namespace
		$namespace = '';
		$href = $titleSplit[0];
		$fullHref = stripslashes($titleSplit[0]);
		
		// Check for an explicit namespace
		$nsSplit = array_map(
			'stripslashes',
			preg_split('/(?<!\\\)\:/', $href, 2)
		);

		if (count($nsSplit) > 1) :
			$namespace = $nsSplit[0];
			$href = $nsSplit[1];
		endif;
    
		// Default case: title based on href
		$title = stripslashes($href) . $suffix;
		$emptyTitle = false;
    	    
		// If there is an explicit title or an empty title
		if (count($titleSplit) > 1) :
			$title = $titleSplit[1] . $suffix;
			$emptyTitle = (strlen($title) == 0);
		endif;
    	    

		// Default transformations.
		if ($emptyTitle) :
			// Get title from href.
			$title = $href . $suffix;
			
			// Namespace has already been stripped off.
			
			// Strip off path, if any
			$title = preg_replace('|^[^/]*?/|','', $title);
				
			// Strip of disambiguator, if any
			$title = preg_replace('/\(.*?\)\s*$/','',$title);
		endif;
        
		if ($namespace=='Image') :
			$options = explode('|',$title);
			$title = array_pop($options);
			
			return $this->handle_image($href,$title,$options);
		elseif (strlen($namespace) and isset($interWiki[strtolower($namespace)])) :
			$namespace = strtolower($namespace);
			$iw = $interWiki[$namespace];
			while (!is_array($iw)) :
				if (is_string($iw)) :
					$iw = strtolower($iw);
				endif;
				
				if (isset($interWiki[$iw])) :
					$iw = $interWiki[$iw];
				else :
					$iw = array();
				endif;
			endwhile;
			
			$slugs = array();
			$slugs[0] = $href;
			$slugs[1] = ucfirst(str_replace(' ', '_', $href));
			$slugs[2] = sanitize_title($href);
				
			$newhref = $iw['q'];
			foreach ($slugs as $i => $slug) :
				$newhref = str_replace('$'.$i, implode("/", array_map('urlencode', explode("/", $slug))), $newhref);
			endforeach;
			return sprintf('<a href="%s" title="%s">%s</a>', esc_url($newhref), $namespace . ": " . $href, $title);
		endif;

		$href = $this->toWikiHref($href);
		$slug = sanitize_title($href);
		
		$wiki = $wpdb->get_var(
			'SELECT `p`.`id` FROM `' . $wpdb->posts . '` `p`
			WHERE `p`.`post_type` = "wiki" AND `p`.`post_name` = "' . $slug  .'"'
		);

		// As in MediaWiki, self-linking = bold
		if (is_object($post) and $post->post_name==$slug) :
			return sprintf('<strong>%s</strong>', $title);
		endif;

		if(!$wiki)
			$redlink = 'style="color:red"';
		else
			$redlink = false;
		if ($this->reference_wiki) {
			$href = $this->reference_wiki.$this->wiki_link($href,$namespace);
		} else {
			$nolink = true;
		}
			
		if ($nolink) return $title;
		
		return sprintf(
			'<a %s href="%s"%s>%s</a>',
			$redlink,
			$href,
			($newwindow?' target="_blank"':''),
			$title
		);
	}
	
	function parse_line($line) {
		$line_regexes = array(
			//'preformat'=>'^\s(.*?)$',
			'definitionlist'=>'^([\;\:])\s*(.*?)$',
			'newline'=>'^$',
			'list'=>'^([\*\#]+)(.*?)$',
			'sections'=>'^(={1,6})(.*?)(={1,6})$',
			'horizontalrule'=>'^----$',
		);
		$char_regexes = array(
			//'link'=>'(\[\[((.*?)\:)?(.*?)(\|(.*?))?\]\]([a-z]+)?)',
			'internallink'=>'('.
				'\[\['. // opening brackets
				'([^\]]+)' . // contents
				'\]\]'. // closing brackets
				'([a-z]+)?'. // any suffixes
				')',
			'externallink'=>'('.
				'(?<!\\\)\['.
					'([^\]]*?)'.
					'(\s+[^\]]*?)?'.
				'\]'.
				')',
			'escapedlink'=>'('.
				'(?=\\\).(\['.
					'([^\]]*?)'.
					'(\s+[^\]]*?)?'.
				'\])'.
				')',
			'emphasize'=>'(\'{2,5})',
			'eliminate'=>'(__TOC__|__NOTOC__|__NOEDITSECTION__)',
			'variable'=>'('. '\{\{' . '([^\}]*?)' . '\}\}' . ')',
		);
				
		$this->stop = false;
		$this->stop_all = false;

		$called = array();
		
		//$line = trim($line);
		
		foreach ($line_regexes as $func=>$regex) {
			if (preg_match("\007" . $regex ."\007ix",$line,$matches)) {
				$called[$func] = true;
				$func = "handle_".$func;
				$line = $this->$func($matches);
				if ($this->stop || $this->stop_all) break;
			}
		}
		
		if (!$this->stop_all) {
			$this->stop = false;
			foreach ($char_regexes as $func=>$regex) {
				// PHP Warning:  preg_replace_callback(): Requires argument 2, 'WPW_WikiParser::handle_0', to be a valid callback in /var/www/wordpress/wp-content/plugins/WikiWikiWordPress/lib/wpw_wikiparser.php on line 237, referer: http://feedwordpress.radgeek.com/wiki/how-do-i-make-the-links-within-a-syndicated-post-open-in-a-new-browser-window/comment-page-1/#comment-889149
				$line = preg_replace_callback("/$regex/i",array($this,"handle_".$func),$line);
				if ($this->stop) break;
			}
		}

		$isline = strlen(trim($line))>0;
		
		// if this wasn't a list item, and we are in a list, close the list tag(s)
		if (($this->list_level>0) && !$called['list']) $line = $this->handle_list(false,true) . $line;
		if ($this->deflist && !$called['definitionlist']) $line = $this->handle_definitionlist(false,true) . $line;
		if ($this->preformat && !$called['preformat']) $line = $this->handle_preformat(false,true) . $line;
		
		// suppress linebreaks for the next line if we just displayed one; otherwise re-enable them
		if ($isline) $this->suppress_linebreaks = ($called['newline'] || $called['sections']);
		
		return $line."\n";
	}

}
?>