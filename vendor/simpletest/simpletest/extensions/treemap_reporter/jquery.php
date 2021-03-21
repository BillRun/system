<?php

require_once __DIR__ . '/../treemap_reporter.php';

/**
 * Outputs unordered list representing treemap of test report,
 * and attaches jQuery Treemap to render results.
 */
class JqueryTreemapReporter extends TreemapReporter
{
    public function _getCss()
    {
        $css = '.treemapView { color:white; }
				.treemapCell {background-color:green;font-size:10px;font-family:Arial;}
  				.treemapHead {cursor:pointer;background-color:#B34700}
				.treemapCell.selected, .treemapCell.selected .treemapCell.selected {background-color:#FFCC80}
  				.treemapCell.selected .treemapCell {background-color:#FF9900}
  				.treemapCell.selected .treemapHead {background-color:#B36B00}
  				.transfer {border:1px solid black}';

        return $css;
    }

    /**
     * Render the results header.
     *
     * @todo  Check URLs of JS. Find repo/alternative for treemap.js.
     *
     * @return string HTML of results header.
     */
    public function paintResultsHeader()
    {
        $title = $this->_reporter->getTitle();
        echo '<html><head>';
        echo "<title>{$title}</title>";
        echo '<style type="text/css">' . $this->_getCss() . '</style>';
        echo '<script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>';
        echo '<script type="text/javascript" src="http://www.fbtools.com/jquery/treemap/treemap.js"></script>';
        echo "<script type=\"text/javascript\">\n";
        echo '	window.onload = function() { jQuery("ul").treemap(800,600,{getData:getDataFromUL}); };
					function getDataFromUL(el) {
					 var data = [];
					 jQuery("li",el).each(function(){
					   var item = jQuery(this);
					   var row = [item.find("span.desc").html(),item.find("span.data").html()];
					   data.push(row);
					 });
					 return data;
					}';
        echo '</script></head>';
        echo '<body><ul>';
    }

    public function paintRectangleStart($node)
    {
        echo '<li><span class="desc">' . basename($node->getDescription()) . '</span>';
        echo '<span class="data">' . $node->getTotalSize() . '</span>';
    }

    public function paintRectangleEnd()
    {
    }

    public function paintResultsFooter()
    {
        echo '</ul></body>';
        echo '</html>';
    }

    public function divideMapNodes($map)
    {
        foreach ($map->getChildren() as $node) {
            if (!$node->isLeaf()) {
                $this->paintRectangleStart($node);
                $this->divideMapNodes($node);
            }
        }
    }
}
