<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 * @link      <http://www.gnu.org/licenses/>.
 * @author    mike
 * @copyright 2014 nZEDb
 */

require_once 'simple_html_dom.php';

/**
 * Class adultdvdempire
 */
class adultdvdempire
{

	/* If a release matches define it as as true = gives callback to continue */
	public $found = null;

	/* Get and compare searchterm */
	public $searchterm = null;

	/* Define param if trailing url is found get it and set it for future calls */
	/* Anything after the $ade url is trailing */
	protected $urlfound = null;

	/* Define ADE Url here */
	const ade = "http://www.adultdvdempire.com";

	/* Trailing urls */
	protected $dvdquery = "/dvd/search?q=";
	protected $allquery = "/allsearch/search?q=";
	protected $scenes = "/scenes";
	protected $boxcover = "/boxcover";
	protected $backcover = "/backcover";
	protected $reviews = "/reviews";
	protected $trailers = "/trailers";

	public function __construct($echooutput = true)
	{
		$this->echooutput = ($echooutput && nZEDb_ECHOCLI);
		$this->url = null;
		$this->response = array();
		$this->tmprsp = null;
		$this->html = new simple_html_dom();
		$this->edithtml = new simple_html_dom();
	}

	/**
	 * Gets Trailer Movies -- Need layout change
	 * Todo: Make layout work with the player/Download swf?
	 * @return array|bool - url, streamid, basestreamingurl
	 */
	public function trailers()
	{
		$res = array();
		$this->getadeurl($this->trailers . $this->urlfound);
		$this->html->load($this->response);
		if (preg_match("/(\"|')(?P<swf>[^\"']+.swf)(\"|')/i", $this->response, $matches)) {
			$res['trailers']['url'] = SELF::ade . trim(trim($matches['swf']), '"');
			if (preg_match("#(?:streamID:\s\")(?P<streamid>[0-9A-Z]+)(?:\")#",
						   $this->response,
						   $matches)
			) {
				$res['trailers']['streamid'] = trim($matches['streamid']);
			}
			if (preg_match("#(?:BaseStreamingUrl:\s\")(?P<baseurl>[0-9]+.[0-9]+.[0-9]+.[0-9]+)(?:\")#",
						   $this->response,
						   $matches)
			) {
				$res['trailers']['baseurl'] = $matches['baseurl'];
			}
		} else {
			return false;
		}
		unset($matches);
		$this->html->clear();

		return $res;
	}

	/**
	 * Gets cover images for the xxx release
	 * @return array - Boxcover and backcover
	 */
	public function covers()
	{
		$res = array();
		$this->getadeurl($this->boxcover . $this->urlfound);
		$this->html->load($this->response);
		foreach ($this->html->find("div[id=FrontBoxCover], img[itemprop=image]") as $img) {
			if (stristr($img->src, "h.jpg")) {
				$res['boxcover'] = $img->src;
				break;
			}
		}
		$this->getadeurl($this->backcover . $this->urlfound);
		$this->html->load($this->response);
		foreach ($this->html->find("div[id=BackBoxCover], img[itemprop=image]") as $img) {
			if (stristr($img->src, "bh.jpg")) {
				$res['backcover'] = $img->src;
				break;
			}
		}
		unset($img);
		$this->html->clear();

		return $res;
	}

	/**
	 * Gets the sypnosis and tagline
	 *
	 * @param bool $tagline - Include tagline? true/false
	 *
	 * @return array - plot,tagline
	 */
	public function sypnosis($tagline = false)
	{
		$res = array();
		if ($tagline === true) {
			$ret = $this->html->find("p.Tagline", 0);
			if (isset($ret->plaintext)) {
				$res['Tagline'] = trim($ret->plaintext);
			}
		}
		if ($this->html->find("p.Tagline", 0)->next_sibling()->next_sibling()) {
			$ret = $this->html->find("p.Tagline", 0)->next_sibling()->next_sibling();
			$res['sypnosis'] = trim($ret->innertext);
		}

		return $res;
	}

	/**
	 * Gets the cast members and/or awards
	 *
	 * @param bool $awards - Include Awards? true/false
	 *
	 * @return array - cast,awards
	 */
	public function cast($awards = false)
	{
		$res = array();
		$this->tmprsp = str_ireplace("Section Cast", "scast", $this->response);
		$this->edithtml->load($this->tmprsp);
		$ret = $this->edithtml->find("div[class=scast]", 0);
		//var_dump($ret); exit;
		$this->tmprsp = trim($ret->outertext);
		$ret = $this->edithtml->load($this->tmprsp);
		foreach ($ret->find("a.PerformerName") as $a) {
			if($a->plaintext != "(bio)"){
			$res['cast'][] = trim($a->plaintext);
			}
		}
		if ($awards == true) {
			if ($ret->find("ul", 1)) {
				foreach ($ret->find("ul", 1)->find("li, strong") as $li) {
					$res['awards'][] = trim($li->plaintext);
				}
			}
		}
		$this->edithtml->clear();
		unset($ret);
		unset($this->tmprsp);

		return $res;
	}

	/**
	 * Gets categories, if exists return array else return false
	 * @return mixed array|bool - Categories, false
	 */
	public function categories()
	{
		$res = array();
		$this->tmprsp = str_ireplace("Section Categories", "scat", $this->response);
		$this->edithtml->load($this->tmprsp);
		$ret = $this->edithtml->find("div[class=scat]", 0);
		$this->tmprsp = trim($ret->outertext);
		$ret = $this->edithtml->load($this->tmprsp);

		foreach ($ret->find("p, a") as $categories) {
			$categories = trim($categories->plaintext);
			if (stristr($categories, ",")) {
				$categories = explode(",", $categories);
				break;
			} else {
				return false;
			}
		}
		$categories = array_map('trim', $categories);
		$res['Categories'] = $categories;
		$this->edithtml->clear();
		unset($this->tmprsp);
		unset($ret);

		return $res;
	}

	/**
	 * Gets Product Information and/or Features
	 *
	 * @param bool $features Include features? true/false
	 *
	 * @return array - ProductInfo/Extras = features
	 */
	public function productinfo($features = false)
	{
		$res = array();
		$dofeature = null;
		$this->tmprsp = str_ireplace("Section ProductInfo", "spdinfo", $this->response);
		$this->edithtml->load($this->tmprsp);
		$ret = $this->edithtml->find("div[class=spdinfo]", 0);
		$this->tmprsp = trim($ret->outertext);
		$ret = $this->edithtml->load($this->tmprsp);
		foreach ($ret->find("text") as $strong) {
			if (trim($strong->innertext) == "Features") {
				$dofeature = true;
			}
			if ($dofeature != true) {
				if (trim($strong->innertext) != "&nbsp;") {
					$res['ProductInfo'][] = trim($strong->innertext);
				}
			} else {
				if ($features == true) {
					$res['Extras'][] = trim($strong->innertext);
				}
			}
		}
		array_shift($res['ProductInfo']);
		array_shift($res['ProductInfo']);
		$res['ProductInfo'] = array_chunk($res['ProductInfo'], 2, false);
		$this->edithtml->clear();
		unset($this->tmprsp);
		unset($ret);

		return $res;
	}

	/**
	 * Searches xxx name.
	 * @return bool - True if releases has 95% match, else false
	 */
	public function search()
	{
		if (!isset($this->searchterm)) {
			return false;
		}
		if ($this->getadeurl($this->dvdquery . rawurlencode($this->searchterm)) === false) {
			return false;
		} else {
			$this->html->load($this->response);
			unset($this->response);
			$ret = $this->html->find("span.sub strong", 0);
			$ret = (int)$ret->plaintext;
			if (isset($ret)) {
				if ($ret >= 1) {
					$ret = $this->html->find("a.boxcover", 0);
					$title = $ret->title;
					$ret = (string)trim($ret->href);
					similar_text($this->searchterm, $title, $p);
					//$p = levenshtein($this->searchterm, $title);
					if ($p >= 95) {
						$this->found = true;
						$this->urlfound = $ret;
						unset($ret);
						$this->html->clear();
						$this->getadeurl($this->urlfound);
						$this->html->load($this->response);
					} else {
						$this->found = false;

						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
	}

	/**
	 * Gets raw html content using adeurl and any trailing url.
	 *
	 * @param null $trailing - required
	 *
	 * @return bool - true if page has content
	 */
	private function getadeurl($trailing = null)
	{
		if (isset($trailing)) {
			$ch = curl_init(SELF::ade . $trailing);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_USERAGENT, "Firefox/2.0.0.1");
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			$this->response = curl_exec($ch);
			if (!$this->response) {
				curl_close($ch);

				return false;
			}
			curl_close($ch);
		} else {
			return false;
		}
	}

	/*
	 * Gets all Information.
	 *
	 * @return array
	 */
	public function _getall()
	{
		$results = array();
		if (is_array($this->sypnosis(true))) {
			$results = array_merge($results, $this->sypnosis(true));
		}
		if (is_array($this->productinfo(true))) {
			$results = array_merge($results, $this->productinfo(true));
		}
		if (is_array($this->cast(true))) {
			$results = array_merge($results, $this->cast(true));
		}
		if (is_array($this->categories())) {
			$results = array_merge($results, $this->categories());
		}
		if (is_array($this->covers())) {
			$results = array_merge($results, $this->covers());
		}
		if (is_array($this->trailers())) {
			$results = array_merge($results, $this->trailers());
		}

		return $results;
	}
}
