<?php
	include(dirname(__FILE__) . "/../config.php");
	include("markdown/markdown.php");
	//	include("geshi/geshi.php");

	function convert_type($type){
		$base = 'object';
		switch($type){
			case 'Namespace':
			case 'namespace': $base='namespace'; break;
			case 'Constructor': $base='constructor'; break;
			case 'Node':
			case 'DOMNode':
			case 'DomNode':   $base='domnode'; break;
			case 'Array':   $base='array'; break;
			case 'Boolean':   $base='boolean'; break;
			case 'Date':    $base='date'; break; 
			case 'Error':     $base='error'; break;
			case 'Function':  $base='function'; break;
			case 'Integer':
			case 'Float':
			case 'int':
			case 'Double':
			case 'integer':
			case 'Number':    $base='number'; break;   
			case 'RegExp':    $base='regexp'; break;
			case 'String':    $base='string'; break;
			default:      $base='object'; break;
		}
		return $base;
	}

	function icon_url($type, $size=16){
		$img = "object";
		switch($type){
			case 'Namespace':
			case 'namespace': $img='namespace'; break;
			case 'Constructor': $img='constructor'; break;
			case 'Node':
			case 'DOMNode':
			case 'DomNode':   $img='domnode'; break;
			case 'Array':   $img='array'; break;
			case 'Boolean':   $img='boolean'; break;
			case 'Date':    $img='date'; break; 
			case 'Error':     $img='error'; break;
			case 'Function':  $img='function'; break;
			case 'Integer':
			case 'Float':
			case 'int':
			case 'Double':
			case 'integer':
			case 'Number':    $img='number'; break;   
			case 'RegExp':    $img='regexp'; break;
			case 'String':    $img='string'; break;
			default:      $img='object'; break;
		}
		return 'css/icons/' . $size . 'x' . $size . '/' . $img . '.png';
	}

	function do_markdown($text){
		//	prep the text and run it through the Markdown parser.
		$lines = explode("\n", $text);
		$fixed = array();
		$b = false;
		foreach($lines as $line){
			//	 pull off the preceding tab.
			$s = $line;
			if(strpos($line, "\t")===0){ $s = substr($s, 1); }
			if(preg_match('/(\t)*\*/', $s)){
				if(!$b){
					$b = true;
					$s = "\n" . $s;
				}
			} else {
				$b = false;
			}
			$fixed[] = $s;
		}
		return Markdown(implode("\n", $fixed));
	}

	function format_example($text){
		//	do this for SyntaxHighlighter use.
		$s = ""; // */ "\n<!--\n" . $text . "\n-->\n";
		//	insert an additional tab if the first character is a tab.
		if(strpos($text, "\t")===0){
			$text = "\t" . $text;
		}
		$lines = explode("\n", "\n" . $text);
		$isCode = false;
		foreach($lines as &$line){
			if(strpos($line, "\t")===0){
				$line = substr($line, 1);	//	always pull off the first tab.
			}
			if(strpos($line, "\t")===0){
				if(!$isCode){
					$isCode = true;
					$line = '<pre class="brush: js;">' . "\n" . $line;
				}
			} else {
				if($isCode){
					$isCode = false;
					$line .= '</pre>';
				}
			}
		}
		if($isCode){
			//	probably we never saw the last line, or the last line was code.
			$lines[] = '</pre>';
		}
		return $s . implode("\n", $lines);
	}

	//	begin the real work.
	if(!isset($version)){ $version = $defVersion; }
	if(!isset($page)){ $page = "dojo"; }

	//	check if there's URL variables
	if(isset($_GET["p"])){
		$page = $_GET["p"];
	}
	if(isset($_GET["v"])){
		$version = $_GET["v"];
	}

	if(strpos($page, "/") > 0){
		$page = implode(".", explode("/", $page));
	}

	//	load up the doc.
	$provides = "provides.xml";
	$resources = "resources.xml";
	$details = "details.xml";
	$data_dir = dirname(__FILE__) . "/../data/" . $version . "/";
	$f = $data_dir . $details;
	if(!file_exists($f)){
		$data_dir = dirname(__FILE__) . "/../data/" . $defVersion . "/";
		$f = $data_dir . $details;
	}
	if(!file_exists($f)){
		echo "API data does not exist for the default version: " . $defVersion . "<br/>";
		exit();
	}

	//	test to see if this has been cached first.
	if(file_exists($data_dir . 'cache/' . $page . '.html')){
		echo file_get_contents($data_dir . 'cache/' . $page . '.html');
		exit();
	}

	//	start the timer.
	$_start = microtime(true);

	//	the main details document.
	$xml = new DOMDocument();
	$xml->load($f);
	$xpath = new DOMXPath($xml);

	//	get our context.
	$context = $xpath->query('//object[@location="' . $page . '"]')->item(0);

	if(!$context){
		$s = '<div style="font-weight: bold;color: #900;">The requested object was not found.</div>';
		echo $s;
		exit();
	}

	//	if we have a context, we can get provides and resources, so do that now.
	$show_require = true;
	$provide = $page;
	$p_xml = new DOMDocument();
	$p_xml->load($data_dir . $provides);
	$p_xpath = new DOMXPath($p_xml);
	$prov = $p_xpath->query('//object[@location="' . $page . '"]/provides/provide');
	if($prov->length > 1){
		$show_require = false;
	}
	else if($prov->length == 1) {
		$provide = $prov->item(0)->nodeValue;
	}

	$show_resource = true;
	$resource = "";
	$r_xml = new DOMDocument();
	$r_xml->load($data_dir . $resources);
	$r_xpath = new DOMXPath($r_xml);
	$resr = $r_xpath->query('//object[@location="' . $page . '"]/resources/resource');
	if($resr->length > 1){
		$show_resource = false;
	} else if($resr->length == 1) {
		$resource = $resr->item(0)->nodeValue;
	}

	//	figure out a few things first.
	$is_constructor = ($context->getAttribute("type")=="Function" && $context->getAttribute("classlike")=="true");
	$nl = $xpath->query('//object[starts-with(@location, "' . $page . '.") and not(starts-with(substring-after(@location, "' . $page . '."), "_"))]');
	$is_namespace = ($nl->length > 0);
	$type = $context->getAttribute("type");
	if(!strlen($type)){ $type = 'Object'; }
	if($is_constructor){ $type = 'Constructor'; }
//	if($is_namespace){ $type = 'Namespace'; }

	//	start up the output process.
	$s = '<div class="jsdoc-permalink" style="display:none;">' . $version . '/' . implode('/', explode(".", $page)) . '</div>';

	//	page heading.
	$s .= '<h1 class="jsdoc-title">'
		.'<img class="trans-icon" border="0" width="36" height="36" src="'
		. $_base_url . icon_url($type, 36)
		. '" alt="' . $type . '" />' . $context->getAttribute("location") 
		. ' <span style="font-size:11px;color:#999;">(version ' . $version . ')</span>'
		. '</h1>';

	//	breadcrumbs and prototype chain
	$protos = array();
	$bc = array($context->getAttribute("location"));
	$node = $context;
	while($node && $node->getAttribute("superclass")){
		$sc = $node->getAttribute("superclass");
		$bc[] = $sc;
		$protos[$sc] = $node;
		$node = $xpath->query('//object[@location="' . $sc . '"]')->item(0);
	}
	$bc = array_reverse($bc);

	$s .= '<div class="jsdoc-prototype">Object';
	foreach($bc as $p){
		$s .= ' &raquo; ';
		if($p != $page){
			$name = implode("/", explode(".", $p));
			$s .= '<a class="jsdoc-link" href="/' . $version . '/' . $name . '">' . $p . '</a>';
		} else {
			$s .= $p;
		}
	}
	$s .= '</div>';

	//	require
	if($page == "dojo"){
		$s .= '<div class="jsdoc-require">&lt;script src="path/to/dojo.js"&gt;&lt;/script&gt;</div>';
	} else if($show_require) {
		$s .= '<div class="jsdoc-require">dojo.require("' . $provide . '");</div>';
	}

	if($show_resource){
		$s .= '<div class="jsdoc-prototype">Defined in ' . $resource . '</div>';
	}

	//	description.  XML doesn't have summary for some reason.
	$desc = $xpath->query("description/text()", $context)->item(0);
	if($desc){
		$s .= '<div class="jsdoc-full-summary">'
			. do_markdown($desc->nodeValue)
			. "</div>";
	} else {
		$desc = $xpath->query("summary/text()", $context)->item(0);
		if($desc){
			$s .= '<div class="jsdoc-full-summary">'
				. do_markdown($desc->nodeValue)
				. "</div>";
		}
	}

	//	examples.
	$examples = $xpath->query("examples/example", $context);
	if($examples->length > 0){
		$s .= '<div class="jsdoc-examples">'
			. '<h2>Examples:</h2>';
		$counter = 1;
		foreach($examples as $example){
			$s .= '<div class="jsdoc-example">'
				. '<h3>Example ' . $counter++ . '</h3>'
				. format_example($example->nodeValue)
				. '</div>';
		}
		$s .= '</div>';
	}

	//	now it gets ugly.  We need to go get all the properties and methods of ourselves,
	//	plus anything in the prototype chain (i.e. superclass), PLUS anything in the mixins list,
	//	and merge them all together, AND make sure they are unique.  On top of that, we need
	//	to make sure we're getting that info from the top to the bottom.
	$mixins = array();
	$props = array();
	$methods = array();

	//	start with getting the mixins.
	$nl = $xpath->query("mixins/mixin[@scope='instance']", $context);
	foreach($nl as $m){
		//	again, this is ugly.
		$m_test = $xpath->query("//object[@location='" . $m->getAttribute("location") . "']");
		if($m_test->length){
			$mixins[$m->getAttribute("location")] = $m_test->item(0);
		}
	}

	//	output the mixin list
	$tmp = array();
	foreach($mixins as $key=>$node){
		//	TODO: don't build the link if it's not part of the fearsome threesome.
		$name = implode("/", explode('.', $key));
		$tmp[] = '<a class="jsdoc-link" href="/' . $version . '/' . $name . '">' . $key . '</a>';
	}
	if(count($tmp)){
		$s .= '<div class="jsdoc-mixins"><label>mixins: </label>'
			. implode(", ", $tmp)
			. '</div>';
	}


	//	and now, this is how we shall do the inheritance!
	$protos = array_reverse($protos);
	$chains = array_merge($protos, $mixins);

	//	now let's get our props and methods.
	foreach($chains as $location=>$node){
		//	properties
		$nl = $xpath->query("properties/property", $node);
		foreach($nl as $n){
			$nm = $n->getAttribute("name");
			$private = $n->getAttribute("private") == "true";
			if(!$private && isset($filter_privates) && $filter_privates && strpos($nm, "_")===0){
				$private = true;
			}
			if(array_key_exists($nm, $props)){
				//	next one up in the chain overrides the original.
				$props[$nm]["scope"] = $n->getAttribute("scope");
				$props[$nm]["type"] = $n->getAttribute("type");
				$props[$nm]["defines"][] = $location;
			} else {
				$props[$nm] = array(
					"name"=>$nm,
					"scope"=>$n->getAttribute("scope"),
					"visibility"=>($private == true ? "private" : "public"),
					"type"=>$n->getAttribute("type"),
					"defines"=>array($location),
					"override"=>false
				);
			}

			if($n->getElementsByTagName("summary")->length){
				$desc = trim($n->getElementsByTagName("summary")->item(0)->nodeValue);
				if(strlen($desc)){
					$props[$nm]["summary"] = $desc;
				}
			}
			if($n->getElementsByTagName("description")->length){
				$desc = trim($n->getElementsByTagName("description")->item(0)->nodeValue);
				if(strlen($desc)){
					$props[$nm]["description"] = do_markdown($desc);
				}
			}
		}

		//	methods
		$nl = $xpath->query("methods/method[not(@constructor)]", $node);
		foreach($nl as $n){
			$nm = $n->getAttribute("name");
			$private = $n->getAttribute("private") == "true";
			if(!$private && isset($filter_privates) && $filter_privates && strpos($nm, "_")===0){
				$private = true;
			}
			if(array_key_exists($nm, $methods)){
				//	next one up in the chain overrides the original.
				$methods[$nm]["scope"] = $n->getAttribute("scope");
				$methods[$nm]["defines"][] = $location;
			} else {
				$methods[$nm] = array(
					"name"=>$nm,
					"scope"=>$n->getAttribute("scope"),
					"visibility"=>($private=="true"?"private":"public"),
					"parameters"=>array(),
					"return-types"=>array(),
					"defines"=>array($location),
					"override"=>false
				);
			}

			if($n->getElementsByTagName("summary")->length){
				$desc = trim($n->getElementsByTagName("summary")->item(0)->nodeValue);
				if(strlen($desc)){
					$methods[$nm]["summary"] = $desc;
				}
			}
			if($n->getElementsByTagName("description")->length){
				$desc = trim($n->getElementsByTagName("description")->item(0)->nodeValue);
				if(strlen($desc)){
					$methods[$nm]["description"] = do_markdown($desc);
				}
			}
			$ex = $n->getElementsByTagName("example");
			if($ex->length){
				if(!array_key_exists("examples", $methods[$nm])){
					$methods[$nm]["examples"] = array();
				}
				foreach($ex as $example){
					$methods[$nm]["examples"][] = format_example($example->nodeValue);
				}
			}
			if($n->getElementsByTagName("return-description")->length){
				$desc = trim($n->getElementsByTagName("return-description")->item(0)->nodeValue);
				if(strlen($desc)){
					$methods[$nm]["return-description"] = $desc;
				}
			}

			//	do up the parameters and the return types.
			$params = $xpath->query("parameters/parameter", $n);
			if($params->length){
				//	TODO: double-check that the XML will always have this.
				$methods[$nm]["parameters"] = array();
				foreach($params as $param){
					$item = array(
						"name"=>$param->getAttribute("name"),
						"type"=>$param->getAttribute("type"),
						"usage"=>$param->getAttribute("usage"),
						"description"=>""
					);
					if($param->getElementsByTagName("summary")->length){
						$desc = trim($param->getElementsByTagName("summary")->item(0)->nodeValue);
						if(strlen($desc)){
							$item["description"] = $desc;
						}
					}
					$methods[$nm]["parameters"][] = $item;
				}
			}

			$rets = $xpath->query("return-types/return-type", $n);
			if($rets->length){
				//	TODO: double-check that the XML will always have this.
				$methods[$nm]["return-types"] = array();
				foreach($rets as $ret){
					$item = array(
						"type"=>$ret->getAttribute("type"),
						"description"=>""
					);
					$methods[$nm]["return-types"][] = $item;
				}
			}
		}
	}

	//	now...our properties
	$nl = $xpath->query("properties/property", $context);
	foreach($nl as $n){
		$nm = $n->getAttribute("name");
		$private = $n->getAttribute("private") == "true";
		if(!$private && isset($filter_privates) && $filter_privates && strpos($nm, "_")===0){
			$private = true;
		}

		if(array_key_exists($nm, $props)){
			//	next one up in the chain overrides the original.
			$props[$nm]["scope"] = $n->getAttribute("scope");
			$props[$nm]["type"] = $n->getAttribute("type");
			$props[$nm]["override"] = true;
		} else {
			$props[$nm] = array(
				"name"=>$nm,
				"scope"=>$n->getAttribute("scope"),
				"visibility"=>($private=="true"?"private":"public"),
				"type"=>$n->getAttribute("type"),
				"defines"=>array(),
				"override"=>false
			);
		}

		if($n->getElementsByTagName("summary")->length){
			$desc = trim($n->getElementsByTagName("summary")->item(0)->nodeValue);
			if(strlen($desc)){
				$props[$nm]["summary"] = $desc;
			}
		}
		if($n->getElementsByTagName("description")->length){
			$desc = trim($n->getElementsByTagName("description")->item(0)->nodeValue);
			if(strlen($desc)){
				$props[$nm]["description"] = do_markdown($desc);
			}
		}
	}

	//	methods
	$nl = $xpath->query("methods/method[not(@constructor)]", $context);
	foreach($nl as $n){
		$nm = $n->getAttribute("name");
		$private = $n->getAttribute("private") == "true";
		if(!$private && isset($filter_privates) && $filter_privates && strpos($nm, "_")===0){
			$private = true;
		}

		if(array_key_exists($nm, $methods)){
			//	next one up in the chain overrides the original.
			$methods[$nm]["scope"] = $n->getAttribute("scope");
			$methods[$nm]["override"] = true;
		} else {
			$methods[$nm] = array(
				"name"=>$nm,
				"scope"=>$n->getAttribute("scope"),
				"visibility"=>($private=="true"?"private":"public"),
				"parameters"=>array(),
				"return-types"=>array(),
				"defines"=>array(),
				"override"=>false
			);
		}

		if($n->getElementsByTagName("summary")->length){
			$desc = trim($n->getElementsByTagName("summary")->item(0)->nodeValue);
			if(strlen($desc)){
				$methods[$nm]["summary"] = $desc;
			}
		}
		if($n->getElementsByTagName("description")->length){
			$desc = trim($n->getElementsByTagName("description")->item(0)->nodeValue);
			if(strlen($desc)){
				$methods[$nm]["description"] = do_markdown($desc);
			}
		}
		if($n->getElementsByTagName("return-description")->length){
			$desc = trim($n->getElementsByTagName("return-description")->item(0)->nodeValue);
			if(strlen($desc)){
				$methods[$nm]["return-description"] = $desc;
			}
		}

		$ex = $n->getElementsByTagName("example");
		if($ex->length){
			if(!array_key_exists("examples", $methods[$nm])){
				$methods[$nm]["examples"] = array();
			}
			foreach($ex as $example){
				$methods[$nm]["examples"][] = format_example($example->nodeValue);
			}
		}

		//	do up the parameters and the return types.
		$params = $xpath->query("parameters/parameter", $n);
		if($params->length){
			//	TODO: double-check that the XML will always have this.
			$methods[$nm]["parameters"] = array();
			foreach($params as $param){
				$item = array(
					"name"=>$param->getAttribute("name"),
					"type"=>$param->getAttribute("type"),
					"usage"=>$param->getAttribute("usage"),
					"description"=>""
				);
				if($param->getElementsByTagName("summary")->length){
					$desc = trim($param->getElementsByTagName("summary")->item(0)->nodeValue);
					if(strlen($desc)){
						$item["description"] = $desc;
					}
				}
				$methods[$nm]["parameters"][] = $item;
			}
		}

		$rets = $xpath->query("return-types/return-type", $n);
		if($rets->length){
			//	TODO: double-check that the XML will always have this.
			$methods[$nm]["return-types"] = array();
			foreach($rets as $ret){
				$item = array(
					"type"=>$ret->getAttribute("type"),
					"description"=>""
				);
				$methods[$nm]["return-types"][] = $item;
			}
		}
	}

	//	ok, now go through all the methods and split out the events as "special" methods.  We're simply going
	//	to be looking for any method prefaced with "on", and put them in a special array.
	function is_event($item){
		return strpos($item["name"], "on") === 0;
	}
	function is_method($item){
		return strpos($item["name"], "on") !== 0;
	}

	$events = array_filter($methods, "is_event");
	$methods = array_filter($methods, "is_method");

	//	ok, now that (in theory) we have all the properties and methods, let's output them.
	//	properties first; the way we'd done the old API tool is semantically correct but unfortunately
	//	the XML documentation doesn't really give us much of the namespace/constructor determinations,
	//	mostly because of the flat structure and the full locations.
	$s .= '<div class="jsdoc-children">';
	$s .= '<div class="jsdoc-field-list">';
	$details = '<div class="jsdoc-children">'
		. '<div class="jsdoc-fields">';
	$field_counter = 0;
	if(count($props) || count($methods) || count($events)){
		if(count($props)){
			$s .= '<h2 class="jsdoc-summary-heading">Property Summary <span class="jsdoc-summary-toggle"></span></h2>'
				. '<div class="jsdoc-summary-list">'
				. '<ul>';
			$details .= '<h2>Properties</h2>';
			ksort($props);
			foreach($props as $name=>$prop){
				$s .= '<li class="' . convert_type($prop["type"]) . 'Icon16 '
					. (isset($prop["visibility"]) ? $prop["visibility"] : 'public') . ' '
					. (isset($prop["defines"]) && count($prop["defines"]) && !$prop["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<a class="inline-link" href="#' . $name . '">'
					. $name
					. '</a>';
				$details .= '<div class="jsdoc-field '
					. (isset($prop["visibility"]) ? $prop["visibility"] : 'public') . ' '
					. (isset($prop["defines"]) && count($prop["defines"]) && !$prop["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<div class="jsdoc-title">'
					. '<span>'
					. '<img class="trans-icon" src="' . $_base_url . icon_url($prop["type"]) . '" alt="' . $prop["type"] . '" border="0" />'
					. '</span>'
					. '<a name="' . $name . '"></a>'
					. $name
					. '</div>';

				//	inheritance list.
				if(isset($prop["defines"]) && count($prop["defines"])){
					$tmp = array();
					foreach($prop["defines"] as $def){
						$tmp[] = '<a class="jsdoc-link" href="/' . $version . '/' . implode("/", explode(".", $def)) . '">'
							. $def
							. '</a>';
					}
				/*
					$s .= '<span class="jsdoc-inheritance">'
						. ($prop["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp)
						. '</span>';
				*/
					$details .= '<div class="jsdoc-inheritance">'
						. ($prop["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp)
						. '</div>';
				}
				if(array_key_exists("description", $prop)){
					$details .= '<div class="jsdoc-summary">' . $prop["description"] . '</div>';
				} else if(array_key_exists("summary", $prop)){
					$details .= '<div class="jsdoc-summary">' . $prop["summary"] . '</div>';
				}
				if(array_key_exists("summary", $prop)){
					$s .= ' <span>' . $prop["summary"] . '</span>';
				}
				$s .= '</li>';	//	jsdoc-title
				$details .= '</div>';	//	jsdoc-field
				$field_counter++;
			}

			$s .= '</ul></div>';	//	property-summary
		}

		if(count($methods)){
			$s .= '<h2 class="jsdoc-summary-heading">Method Summary <span class="jsdoc-summary-toggle"></span></h2>'
				. '<div class="jsdoc-summary-list">'
				. '<ul>';
			$details .= '<h2>Methods</h2>';
			ksort($methods);
			foreach($methods as $name=>$method){
				$s .= '<li class="functionIcon16 '
					. (isset($method["visibility"]) ? $method["visibility"] : 'public') . ' '
					. (isset($method["defines"]) && count($method["defines"]) && !$method["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<a class="inline-link" href="#' . $name . '">'
					. $name
					. '</a>';
				$details .= '<div class="jsdoc-field '
					. (isset($method["visibility"]) ? $method["visibility"] : 'public') . ' '
					. (isset($method["defines"]) && count($method["defines"]) && !$method["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<div class="jsdoc-title">'
					. '<span>'
					. '<img class="trans-icon" src="' . $_base_url . icon_url('Function') . '" alt="Function" border="0" />'
					. '</span>'
					. '<a name="' . $name . '"></a>'
					. $name
					. '</div>';
				if(count($method["parameters"])){
					$tmp = array();
					foreach($method["parameters"] as $p){
	//					$tmp[] = '<span class="jsdoc-comment-type">/*'
	//						. (strlen($p["type"]) ? $p["type"] : 'Object')
	//						. (strlen($p["usage"]) ? (($p["usage"] == "optional") ? '?' : (($p["usage"] == "one-or-more") ? '...' : '')) : '')
	//						. '*/</span>'
	//						. $p["name"];
						$tmp[] = $p["name"];
					}
					$s .= '<span class="parameters">('
						. implode(', ', $tmp)
						. ')</span>';
				} else {
					$s .= '<span class="parameters">()</span>';
				}

				if(count($method["return-types"])){
					$tmp = array();
					foreach($method["return-types"] as $rt){
						$tmp[] = $rt["type"];
					}
					/*
					$s .= ' &rArr; <span class="jsdoc-return-type">'
						. implode("|", $tmp) 
						. '</span>';
					*/
					$s .= '<span class="jsdoc-returns"> returns ' . implode("|", $tmp) . '</span>';
				}

				//	inheritance list.
				if(isset($prop["defines"]) && count($method["defines"])){
					$tmp = array();
					foreach($method["defines"] as $def){
						$tmp[] = '<a class="jsdoc-link" href="/' . $version . '/' . implode("/", explode(".", $def)) . '">'
							. $def
							. '</a>';
					}
/*
					$s .= '<span class="jsdoc-inheritance">'
						. ($method["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp) 
						. '</span>';	//	jsdoc-inheritance
*/
					$details .= '<div class="jsdoc-inheritance">'
						. ($method["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp) 
						. '</div>';	//	jsdoc-inheritance
				}

				if(count($method["return-types"])){
					$tmp = array();
					foreach($method["return-types"] as $rt){
						$tmp[] = $rt["type"];
					}
					$details .= '<div class="jsdoc-return-type">Returns '
						. '<strong>'
						. implode("|", $tmp)
						. '</strong>';
					if(array_key_exists("return-description", $method)){
						$details .= ': <span class="jsdoc-return-description">'
							. $method["return-description"]
							. '</span>';
					}
					$details .= '</div>';
				} 
				else if(array_key_exists("return-description", $method)){
					$details .= '<div class="jsdoc-return-type"><div class="jsdoc-return-description">'
						. $method["return-description"]
						. '</div></div>';
				}

				if(array_key_exists("description", $method)){
					$details .= '<div class="jsdoc-summary">' . $method["description"] . '</div>';
				} else if(array_key_exists("summary", $method)){
					$details .= '<div class="jsdoc-summary">' . $method["summary"] . '</div>';
				}
				if(array_key_exists("summary", $method)){
					$s .= ' <span>' . $method["summary"] . '</span>';
				}
				$s .= '</li>';	//	jsdoc-title

				if(count($method["parameters"])){
					$tmp_details = array();
					foreach($method["parameters"] as $p){
						$pstr = '<tr>'
							. '<td class="jsdoc-param-name">'
							. $p["name"]
							. '</td>'
							. '<td class="jsdoc-param-type">'
							. $p["type"]
							. '</td>'
							. '<td class="jsdoc-param-description">'
							. (strlen($p["usage"]) ? (($p["usage"] == "optional") ? '<div><em>Optional.</em></div>' : (($p["usage"] == "one-or-more") ? '<div><em>One or more can be passed.</em></div>' : '')) : '')
							. $p["description"];

						$tester = array_pop(explode(".", $p["type"]));
						if(strpos($tester, "__")===0){
							//	try to find the object in question, and if found list out the props.
							$pconfig = $xpath->query("//object[@location='" . $p["type"] . "']");
							if($pconfig->length){
								$p_param = array();
								$p_nodes = $pconfig->item(0)->getElementsByTagName("property");
								foreach($p_nodes as $p_node){
									$summary = $p_node->getElementsByTagName("summary");
									$p_param[] = '<tr>'
										. '<td class="jsdoc-param-name">'
										. $p_node->getAttribute("name")
										. '</td>'
										. '<td class="jsdoc-param-type">'
										. $p_node->getAttribute("type")
										. '</td>'
										. '<td class="jsdoc-param-description">'
										. ($summary->length ? $summary->item(0)->nodeValue : '&nbsp;')
										. '</td>'
										. '</tr>';
								}
								$pstr .= '<table class="jsdoc-parameters" style="margin-left:0;margin-right:0;margin-bottom:0;">'
									. '<tr>'
									. '<th>Parameter</th>'
									. '<th>Type</th>'
									. '<th>Description</th>'
									. '</tr>'
									. implode('', $p_param)
									. '</table>';
							}
						}
						$pstr .= '</td>'
							. '</tr>';
						$tmp_details[] = $pstr;
					}
					$details .= '<table class="jsdoc-parameters">'
						. '<tr>'
						. '<th>Parameter</th>'
						. '<th>Type</th>'
						. '<th>Description</th>'
						. '</tr>'
						. implode('', $tmp_details)
						. '</table>';
				}

				if(array_key_exists("examples", $method)){
					$details .= '<div class="jsdoc-examples">';
					$counter = 1;
					foreach($method["examples"] as $example){
						$details .= '<div class="jsdoc-example">'
							. '<div><strong>Example ' . $counter++ . '</strong></div>'
							. $example
							. '</div>';
					}
					$details .= '</div>';
				}

				$details .= '</div>';	//	jsdoc-field
				$field_counter++;
			}
			$s .= '</ul></div>';	//	method-summary
		}

		if(count($events)){
			$s .= '<h2 class="jsdoc-summary-heading">Event Summary <span class="jsdoc-summary-toggle"></span></h2>'
				. '<div class="jsdoc-summary-list">'
				. '<ul>';
			$details .= '<h2>Events</h2>';
			ksort($events);
			foreach($events as $name=>$method){
				$s .= '<li class="functionIcon16 '
					. (isset($method["visibility"]) ? $method["visibility"] : 'public') . ' '
					. (isset($method["defines"]) && count($method["defines"]) && !$method["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<a class="inline-link" href="#' . $name . '">'
					. $name
					. '</a>';
				$details .= '<div class="jsdoc-field '
					. (isset($method["visibility"]) ? $method["visibility"] : 'public') . ' '
					. (isset($method["defines"]) && count($method["defines"]) && !$method["override"] ? 'inherited':'')
					. ($field_counter % 2 == 0 ? ' even':' odd')
					. '">'
					. '<div class="jsdoc-title">'
					. '<span>'
					. '<img class="trans-icon" src="' . $_base_url . icon_url('Function') . '" alt="Function" border="0" />'
					. '</span>'
					. '<a name="' . $name . '"></a>'
					. $name
					. '</div>';
				if(count($method["parameters"])){
					$tmp = array();
					foreach($method["parameters"] as $p){
	//					$tmp[] = '<span class="jsdoc-comment-type">/*'
	//						. (strlen($p["type"]) ? $p["type"] : 'Object')
	//						. (strlen($p["usage"]) ? (($p["usage"] == "optional") ? '?' : (($p["usage"] == "one-or-more") ? '...' : '')) : '')
	//						. '*/</span>'
	//						. $p["name"];
						$tmp[] = $p["name"];
					}
					$s .= '<span class="parameters">('
						. implode(', ', $tmp)
						. ')</span>';
				} else {
					$s .= '<span class="parameters">()</span>';
				}

				if(count($method["return-types"])){
					$tmp = array();
					foreach($method["return-types"] as $rt){
						$tmp[] = $rt["type"];
					}
					/*
					$s .= ' &rArr; <span class="jsdoc-return-type">'
						. implode("|", $tmp) 
						. '</span>';
					*/
					$s .= '<span class="jsdoc-returns"> returns ' . implode("|", $tmp) . '</span>';
				}

				//	inheritance list.
				if(isset($prop["defines"]) && count($method["defines"])){
					$tmp = array();
					foreach($method["defines"] as $def){
						$tmp[] = '<a class="jsdoc-link" href="/' . $version . '/' . implode("/", explode(".", $def)) . '">'
							. $def
							. '</a>';
					}
/*
					$s .= '<span class="jsdoc-inheritance">'
						. ($method["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp) 
						. '</span>';	//	jsdoc-inheritance
*/
					$details .= '<div class="jsdoc-inheritance">'
						. ($method["override"] ? "Overrides ":"Defined by ")
						. implode(", ", $tmp) 
						. '</div>';	//	jsdoc-inheritance
				}

				if(count($method["return-types"])){
					$tmp = array();
					foreach($method["return-types"] as $rt){
						$tmp[] = $rt["type"];
					}
					$details .= '<div class="jsdoc-return-type">Returns '
						. '<strong>'
						. implode("|", $tmp)
						. '</strong>';
					if(array_key_exists("return-description", $method)){
						$details .= ': <span class="jsdoc-return-description">'
							. $method["return-description"]
							. '</span>';
					}
					$details .= '</div>';
				} 
				else if(array_key_exists("return-description", $method)){
					$details .= '<div class="jsdoc-return-type"><div class="jsdoc-return-description">'
						. $method["return-description"]
						. '</div></div>';
				}

				if(array_key_exists("description", $method)){
					$details .= '<div class="jsdoc-summary">' . $method["description"] . '</div>';
				} else if(array_key_exists("summary", $method)){
					$details .= '<div class="jsdoc-summary">' . $method["summary"] . '</div>';
				}
				if(array_key_exists("summary", $method)){
					$s .= ' <span>' . $method["summary"] . '</span>';
				}
				$s .= '</li>';	//	jsdoc-title

				if(count($method["parameters"])){
					$tmp_details = array();
					foreach($method["parameters"] as $p){
						$pstr = '<tr>'
							. '<td class="jsdoc-param-name">'
							. $p["name"]
							. '</td>'
							. '<td class="jsdoc-param-type">'
							. $p["type"]
							. '</td>'
							. '<td class="jsdoc-param-description">'
							. (strlen($p["usage"]) ? (($p["usage"] == "optional") ? '<div><em>Optional.</em></div>' : (($p["usage"] == "one-or-more") ? '<div><em>One or more can be passed.</em></div>' : '')) : '')
							. $p["description"];

						$tester = array_pop(explode(".", $p["type"]));
						if(strpos($tester, "__")===0){
							//	try to find the object in question, and if found list out the props.
							$pconfig = $xpath->query("//object[@location='" . $p["type"] . "']");
							if($pconfig->length){
								$p_param = array();
								$p_nodes = $pconfig->item(0)->getElementsByTagName("property");
								foreach($p_nodes as $p_node){
									$summary = $p_node->getElementsByTagName("summary");
									$p_param[] = '<tr>'
										. '<td class="jsdoc-param-name">'
										. $p_node->getAttribute("name")
										. '</td>'
										. '<td class="jsdoc-param-type">'
										. $p_node->getAttribute("type")
										. '</td>'
										. '<td class="jsdoc-param-description">'
										. ($summary->length ? $summary->item(0)->nodeValue : '&nbsp;')
										. '</td>'
										. '</tr>';
								}
								$pstr .= '<table class="jsdoc-parameters" style="margin-left:0;margin-right:0;margin-bottom:0;">'
									. '<tr>'
									. '<th>Parameter</th>'
									. '<th>Type</th>'
									. '<th>Description</th>'
									. '</tr>'
									. implode('', $p_param)
									. '</table>';
							}
						}
						$pstr .= '</td>'
							. '</tr>';
						$tmp_details[] = $pstr;
					}
					$details .= '<table class="jsdoc-parameters">'
						. '<tr>'
						. '<th>Parameter</th>'
						. '<th>Type</th>'
						. '<th>Description</th>'
						. '</tr>'
						. implode('', $tmp_details)
						. '</table>';
				}

				if(array_key_exists("examples", $method)){
					$details .= '<div class="jsdoc-examples">';
					$counter = 1;
					foreach($method["examples"] as $example){
						$details .= '<div class="jsdoc-example">'
							. '<div><strong>Example ' . $counter++ . '</strong></div>'
							. $example
							. '</div>';
					}
					$details .= '</div>';
				}

				$details .= '</div>';	//	jsdoc-field
				$field_counter++;
			}
			$s .= '</ul></div>';	//	method-summary
		}
	}

	//	child objects: put up a list of any child objects that are attached to this particular one.
	$children = $xpath->query('//object[starts-with(@location, "' . $page . '.")]');
	if($children->length){
		$s .= '<h2 class="jsdoc-summary-heading">Attached Objects <span class="jsdoc-summary-toggle"></span></h2>'
			. '<div class="jsdoc-summary-list">';
		foreach($children as $child){
			$s .= '<div class="jsdoc-field">'
				. '<div class="jsdoc-title">'
				. '<span>'
				. '<img class="trans-icon" src="'; 

			if($child->getAttribute("type") == "Function" && $child->getAttribute("classlike") == "true"){
				$s .= $_base_url . icon_url("Constructor");
				$alt = "Constructor";
			} else {
				$s .= $_base_url . icon_url($child->getAttribute("type"));
				$alt = $child->getAttribute("type");
			}
			$s .= '" alt="' . $alt . '" border="0" />'
				. '</span>'
				. '<a class="jsdoc-link" href="/' . $version . '/' . implode("/", explode(".", $child->getAttribute("location"))) . '">'
				. $child->getAttribute("location")
				. '</a>'
				. '</div>'	//	jsdoc-title
				. '</div>';	//	jsdoc-field
		}
		$s .= '</div>';
	}

	$s .= '</div>';	// jsdoc-field-list.
	$s .= '</div>';	// jsdoc-children.
	$details .= '</div></div>';
	$_total = round(microtime(true) - $_start, 3);

	//	if we got here, we're not cached so do that now.
	file_put_contents(
		$data_dir . 'cache/' . $page . '.html', 
		$s . $details . "\n<!-- generation time: " . $_total . "s. -->\n"
	);
	
	echo $s . $details;

	//	end timer.
	echo '<div style="text-align:right;font-size:0.85em;">'
		. 'Generation time: '
		. $_total
		. 's.'
		. '</div>';
?>
