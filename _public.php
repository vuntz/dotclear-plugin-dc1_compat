<?php
# ***** BEGIN LICENSE BLOCK *****
# Copyright (c) 2006 Olivier Meunier and contributors. All rights reserved.
# Copyright (c) 2007 Vincent Untz
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****
#
# This plugin is based on dc1redirect:
# http://dev.dotclear.net/2.0/browser/plugins/dc1redirect

/* http://www.vuntz.net/journal/2007/09/10/446-svn-vs-gitbzrhgblablabla */
$core->url->register('redirect_post','','^(\d{4}/\d{2}/\d{2}/\d+.+)$',array('dcUrlRedirect','post'));

/* http://www.vuntz.net/journal/2007/09/10 
 * http://www.vuntz.net/journal/2004/03 */
if ($core->plugins->moduleExists('dayMode') && $core->blog->settings->daymode_active) {
	$archive_pattern = '^(\d{4}/\d{2}(/\d{2})?)/?$';
} else {
	$archive_pattern = '^(\d{4}/\d{2})/?$';
}
$core->url->register('redirect_archive','',$archive_pattern, array('dcUrlRedirect','archive'));
unset($archive_pattern);

/* http://www.vuntz.net/journal/2004/02/p2 */
$core->url->register('redirect_archive_page','','^(\d{4}/\d{2})/p\d+$', array('dcUrlRedirect','archive'));

/* http://www.vuntz.net/journal/Mon-avis */
$core->url->register('redirect_category','','^([A-Z]+[A-Za-z0-9_-]*)/?$',array('dcUrlRedirect','category'));

/* http://www.vuntz.net/journal/Informatique/p2 */
$core->url->register('redirect_category_page','','^([A-Z]+[A-Za-z0-9_-]*/p\d+)$',array('dcUrlRedirect','category_page'));

/* http://www.vuntz.net/journal/p2 */
$core->url->register('redirect_page','','^p(\d+)$',array('dcUrlRedirect','page'));

/* http://www.vuntz.net/journal/rss.php
 * http://www.vuntz.net/journal/rss.php?lang=en
 * http://www.vuntz.net/journal/rss.php?type=co
 * http://www.vuntz.net/journal/rss.php?type=co&post=446 */
$core->url->register('redirect_rss','','^(rss.php)$',array('dcUrlRedirect','rss'));
$core->url->register('redirect_atom','','^(atom.php)$',array('dcUrlRedirect','atom'));

/* http://www.vuntz.net/journal/tb.php?id=446
 * http://www.vuntz.net/journal/tb.php */
$core->url->register('redirect_tb','','^(tb.php)$',array('dcUrlRedirect','tb'));

class dcUrlRedirect extends dcUrlHandlers
{
	private static function redirect_to($dest)
	{
		global $core;

		$url = $core->blog->url.$dest;
		http::head(301,'Moved Permanently');
		header('Location: '.$url);
		exit;
	}

	public static function post($args)
	{
		self::redirect_to("post/".$args);
	}

	public static function archive($args)
	{
		self::redirect_to("archive/".$args);
	}

	public static function category($args)
	{
		self::redirect_to("category/".$args);
	}

	public static function category_page($args)
	{
		$ret = preg_match("@^(.+)/p(\d+)$@", $args, $matches);

		if ($ret === False || count($matches) != 3) {
			self::p404();
		} else {
			$cat = $matches[1];
			$number = $matches[2];
			self::redirect_to("category/".$cat."/page/".$number);
		}
	}

	public static function page($args)
	{
		self::redirect_to("page/".$args);
	}

	private static function myfeed($param, $type)
	{
		$lang = null;
		$category = null;
		$post = null;
		$comments = False;

		$params = explode ("&", $param);

		for ($i = 0; $i < count($params); $i++) {
			$vals = explode ("=", $params[$i]);
			if ($vals === False || count($vals) != 2) {
				continue;
			}

			if ($vals[0] == "lang") {
				if (!empty($vals[1])) {
					$lang = $vals[1];
				}
			} else if ($vals[0] == "cat") {
				if (!empty($vals[1])) {
					$category = $vals[1];
				}
			} else if ($vals[0] == "type") {
				if ($vals[1] == "co") {
					$comments = True;
				}
			} else if ($vals[0] == "post") {
				if (!empty($vals[1])) {
					$post = $vals[1];
				}
			}
		}

		/* Some examples:
		 * http://www.vuntz.net/journal/feed/en/atom
		 * http://www.vuntz.net/journal/feed/en/atom/comments
		 * http://www.vuntz.net/journal/feed/category/Gnome/atom
		 * http://www.vuntz.net/journal/feed/en/category/Gnome/atom
		 * http://www.vuntz.net/journal/feed/category/Gnome/atom/comments
		 * http://www.vuntz.net/journal/feed/atom/comments/446 */

		$url = "feed/";
		if (!empty($lang) && !($comments && empty($category) && !empty($post))) {
			$url = $url.$lang."/";
		}
		if (!empty($category)) {
			$url = $url."category/".$category."/";
		}
		$url = $url.$type;
		if ($comments) {
			$url = $url."/comments";
		}
		if ($comments && empty($category) && !empty($post)) {
			$url = $url."/".$post;
		}

		self::redirect_to("$url");
	}

	public static function rss($args)
	{
		$param = substr ($_SERVER['QUERY_STRING'], strlen ($args));
		self::myfeed($param,"rss2");
	}

	public static function atom($args)
	{
		$param = substr ($_SERVER['QUERY_STRING'], strlen ($args));
		self::myfeed($param,"atom");
	}

	public static function tb($args)
	{
		$param = substr ($_SERVER['QUERY_STRING'], strlen ($args));
		$params = explode ("&", $param);
		
		$id = null;

		for ($i = 0; $i < count($params); $i++) {
			$vals = explode ("=", $params[$i]);
			if ($vals === False || count($vals) != 2 || $vals[0] != "id") {
				continue;
			}

			$id = $vals[1];
		}

		if (!empty($id)) {
			self::redirect_to("trackback/".$id);
		}

		self::p404();
	}
}
?>
