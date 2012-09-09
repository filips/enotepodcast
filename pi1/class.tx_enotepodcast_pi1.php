<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Kasper Skårhøj <kaska@llab.dtu.dk>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'Enote Podcast' for the 'enotepodcast' extension.
 *
 * @author	Kasper Skårhøj <kaska@llab.dtu.dk>
 */

require_once(PATH_tslib.'class.tslib_pibase.php');

class tx_enotepodcast_pi1 extends tslib_pibase {
	var $prefixId = 'tx_enotepodcast_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_enotepodcast_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'enotepodcast';	// The extension key.
	var $pi_checkCHash = TRUE;
	var $podcastLocal = true;
	var $podcastPath;
	var $podcastUrl;
	var $metadata; 
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		
			// Init pi_flexform / plugin configuration.
		$this->pi_initPIflexForm();

		$this->podcastPath = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['path']['vDEF'];
		$this->podcastUrl = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['url']['vDEF'];
		
		if($this->podcastUrl) {
			$this->podcastLocal = false;
		} else {
			$this->metadata	= array(
				"title" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['title']['vDEF'],
				"TYPO3_SITE_URL" => htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
				"copyright" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['copyright']['vDEF'],
				"description>" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['description']['vDEF'],
				"subtitle"=>$this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['subtitle']['vDEF'],
				"author" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['author']['vDEF'],
				"author_email" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['author_email']['vDEF'],
				"image" => (trim($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['image']['vDEF']) ? htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['image']['vDEF']) : ''),
				"category" => $this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['category']['vDEF']
			);
		}

		$GLOBALS['TSFE']->set_no_cache();	
		if($title = $this->piVars['showDetails']) { // Show details for a single podcast episode
			if(!($content = $this->podcastDetails($title))) {
				$content = $this->pi_getLL('errEpisodeNotFound');
			}
		} else { // Show the podcast channel in either HTML or RSS
			$content = $this->podcastFeed();
		}
		return $this->pi_wrapInBaseClass($content);
	}

	function getVersions()	{
		//Get list of video resolutions and explode into an array
		$versions = t3lib_div::trimExplode(',',$this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['versions']['vDEF']);
		$c=0;
		foreach ($versions as $version) {
			// 1: File suffix 3: Internal label 5: Label
			preg_match("/^\s*(\w*)\s*(\(\s*(\w*)\s*(:\s*(.*))?\s*\))?\s*$/",$version,$values);
			if(strlen($values[3])>0 && strtoupper($values[3])){ // Create associative entry in array if internal label is present, otherwise a numbered one
				$entry = & $version_list['feeds'][$values[3]];
			} else {
				if ($values[1]=='720p')	{	// Special case. "720p" will be automatically labeled "HD"
					$entry = & $version_list['feeds']['HD'];
				} else {
					$c++;
					$entry = & $version_list['feeds'][$c];
				}
			}
				$entry['version'] = $values[1];
				$entry['label'] = (strlen($values[4]) >0 ? $values[5] : $values[1]);

		}
		#debug($version_list);
		return $version_list;
	}
	function jsonData($data) {
		$listWidth = intval($this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['thumbnailSize']['vDEF']);
		if ($listWidth<=0)	{$listWidth = 135;}

		$thumbnailSizes = array($listWidth, 250);
		foreach($data as &$entry) {
			foreach($entry['media'] as $key => &$res) {
				if($key == 'thumbnail') {
					foreach($res as $id => &$link) {
						//$link = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . substr($link, strlen(PATH_site));
						if($link){
							$path = substr($link,strlen(PATH_site));
						} else {
							$path = '';
						}
						$res[$id] = array();
						foreach($thumbnailSizes as $size) {
							$res[$id][$size] = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $this->getImage($path,null,$size,true);
						}

					} 
				} else {
					$res['filename'] = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . substr($res['filename'], strlen(PATH_site));
				}
			}
		}
		$data = array_merge(array("metadata" => $this->metadata), $this->getVersions(),array("episodes" => $data));
		$jsonArray = json_encode($data);
		// Send JSON data to user
		
		header('Content-type: application/json');
		header('Content-length: ' . strlen($jsonArray));
		echo $jsonArray;
		exit;
	}
	function RSSfeed($data, $order, $podcast, $onlyPublished) {
		$metadata = $this->metadata;


		$resolution = $podcast['DEFAULT'];
		if ($rss_key = $this->piVars['additionalRSSkey'])	{
			if(array_key_exists($rss_key, $podcast['feeds'])) {
				$titleSuffix = (strlen($podcast['feeds'][$rss_key]['label'])>0 ? '('.$podcast['feeds'][$rss_key]['label'].')':'');
				$resolution = $podcast['feeds'][$rss_key]['version'];
			}
		}
		// Traverse them, compile item list:
		$outputItems = '';
		foreach($order as $idx)	{
			if (!$onlyPublished || $data[$idx]['pubDate']<time()) {
				if (isset($data[$idx]['media'][$resolution])  && $data[$idx]['media'][$resolution]['_lastWrite']>10)	{
					$videourl = $this->getUrl($data[$idx]['media'][$resolution]['filename']);
					$outputItems.= '
				<item>
					<title>'.htmlspecialchars(trim($data[$idx]['title'])).'</title>
					<description>'.htmlspecialchars($data[$idx]['description']).'</description>
					'.($data[$idx]['itunes:keywords']?'<itunes:keywords>"'.htmlspecialchars(str_replace(" ", "",$data[$idx]['itunes:keywords'])).'"</itunes:keywords>':'').'
					'.($data[$idx]['itunes:author']?'<itunes:author>'.htmlspecialchars($data[$idx]['itunes:author']).'</itunes:author>':'').'
					<pubDate>'.htmlspecialchars(strftime('%a, %d %b %Y %T %z',$data[$idx]['pubDate'])).'</pubDate>
					<enclosure url="'.htmlspecialchars(trim($videourl)).'" length="'.htmlspecialchars(trim($data[$idx]['media'][$resolution]['filesize'])).'" type="'.$data[$idx]['media'][$resolution]['mimetype'].'" />
					<guid>'.htmlspecialchars(trim($videourl)).'</guid>
					<itunes:duration>'.htmlspecialchars($data[$idx]['itunes:duration']?$data[$idx]['itunes:duration']:$data[$idx]['media'][$resolution]['duration']).'</itunes:duration>
				</item>
					';
				}
			}
		}
		//http://www.apple.com/itunes/podcasts/specs.html
		/*$outputXML = '<?xml version="1.0" encoding="utf-8"?>
	<rss version="2.0" xmlns:itunes="http://www.itunes.com/DTDs/Podcast-1.0.dtd">
	<channel>
		<title>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['title']['vDEF']).' '.$titleSuffix.'</title>
		<link>'.htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL')).'</link>
		<copyright>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['copyright']['vDEF']).'</copyright>
		<description>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['description']['vDEF']).'</description>
		<itunes:summary>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['description']['vDEF']).'</itunes:summary>
		<itunes:subtitle>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['subtitle']['vDEF']).'</itunes:subtitle>
		<itunes:author>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['author']['vDEF']).'</itunes:author>
		<itunes:owner>
			<itunes:name>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['author']['vDEF']).'</itunes:name>
			<itunes:email>'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['author_email']['vDEF']).'</itunes:email>
		</itunes:owner>
		<itunes:image href="'.(trim($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['image']['vDEF']) ? htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['image']['vDEF']) : '').'" />
		<itunes:category text="'.htmlspecialchars($this->cObj->data['pi_flexform']['data']['sOptions']['lDEF']['category']['vDEF']).'"/>

		<pubDate>'.strftime('%a %e. %b %Y, %H:%M',time()).'</pubDate>
		<ttl>120</ttl>
		<generator>TYPO3, EXT:enotepodcast</generator>

		';*/
			$outputXML = '<?xml version="1.0" encoding="utf-8"?>
	<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
	<channel>
		<title>'.htmlspecialchars($metadata['title']).' '.$titleSuffix.'</title>
		<link>'.$metadata['TYPO3_SITE_URL'].'</link>
		<copyright>'.htmlspecialchars($metadata['copyright']).'</copyright>
		<description>'.htmlspecialchars($metadata['description']).'</description>
		<itunes:summary>'.htmlspecialchars($metadata['description']).'</itunes:summary>
		<itunes:subtitle>'.htmlspecialchars($metadata['subtitle']).'</itunes:subtitle>
		<itunes:author>'.htmlspecialchars($metadata['author']).'</itunes:author>
		<itunes:owner>
			<itunes:name>'.htmlspecialchars($metadata['author']).'</itunes:name>
			<itunes:email>'.htmlspecialchars($metadata['author_email']).'</itunes:email>
		</itunes:owner>'.
		($metadata['image'] ?
		'<itunes:image href="'.$metadata['image'].'" />'
		:'').'
		<itunes:category text="'.htmlspecialchars($metadata['category']).'"/>
		<itunes:explicit>no</itunes:explicit>
		<pubDate>'.date('r').'</pubDate>
		<ttl>120</ttl>
		<generator>TYPO3, EXT:enotepodcast</generator>

		';

		$outputXML.= $outputItems;
		$outputXML.='
		   </channel>
		</rss>';
		
		//Send XML
		header("Content-Type: application/xml; charset=utf-8");	
		echo trim($outputXML);
		exit; //Prevent template html from showing
	}
	function podcastDetails($title) {
		// TODO: Eliminate duplicate code
		
		//Get path to podcast directory
		$thePath = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['path']['vDEF'];

		//Check if the path is within fileadmin/
		if(!(t3lib_div::validPathStr($thePath) && strpos($thePath, 'fileadmin/') == 0)) {
			return ('Error: Invalid path to podcast directory');
		}
		//Get list of podcast files
		list($data,$order,$podcast) = $this->getPodcastData($thePath);
		
		// Find episode with given title
		foreach($data as $key => $values) {
			if (htmlspecialchars(trim($values['title'])) == $title) {
				$podcastKey = $key;
				break;
			}
		}
		if (isset($podcastKey)) {
			$episode = $data[$podcastKey];
			$GLOBALS['TSFE']->page['title'] = $episode['title'];
			$GLOBALS['TSFE']->additionalHeaderData[] = "<meta name='description' content='".$episode['description']."' />";
			if ($episode['pubDate']<time()) {
				$downloadLinks = array();
				$resolutions = $podcast;
				$spilletid = '';
				//Go through video resolutions, generate links if file is present
				foreach($resolutions['feeds'] as $label => $options)	{
					$resolution = $options['version'];
					if (isset($episode['media'][$resolution]))	{
						//File with given resolution exists, and have not been written to the last ten seconds (upload)
						if (isset($episode['media'][$resolution])  && $episode['media'][$resolution]['_lastWrite']>10)	{
							$videourl = $this->getUrl($episode['media'][$resolution]['filename']);
							$downloadLinks[]='<a href="'.htmlspecialchars($videourl).'">'.$resolution.' ('.t3lib_div::formatSize($episode['media'][$resolution]['filesize']).')</a>';
							$spilletid = $episode['media'][$resolution]['duration'];
						} else {
							$downloadLinks[]=$resolution;
						}
					} else { // File doesnt exist, display dummy label
						$downloadLinks[]=$resolution;
					}
				}
				$thumbnailWidth = '250';
				$outputHTML .= "<h3 style='line-height: 1.4em'>" . $episode['title'] . "</h2>";
				$outputHTML .= "<div style='padding-bottom: 15px'><b>" . $this->pi_getLL('duration') . "</b>: " . $spilletid;
				$outputHTML .= "<b style='padding-left: 10px'>".$this->pi_getLL('date')."</b>: ".htmlspecialchars(strftime('%d/%m/%Y %R',$episode['pubDate']));
				$outputHTML .= "<b style='padding-left: 10px'>".$this->pi_getLL('download')."</b>: " . implode(' ', $downloadLinks) . "</div>";
				$outputHTML .= "<div style='overflow: auto'><div style='float: left; padding-right: 10px'>".$this->getImage($episode['media']['thumbnail'],0,$thumbnailWidth,false)."</div>";
				$outputHTML .= $episode['description']."</div>";
				// Check for youtube link
				if ($episode['enotelms:YouTubeUID'])	{ // TODO: Detect video aspect ratio automatically
					$outputHTML .= '<div style="text-align: center;padding-top: 20px;"><object width="480" height="270">
					  <param name="movie"
					         value="http://www.youtube.com/v/'.$episode['enotelms:YouTubeUID'].'?version=3&autohide=1&showinfo=0&modestbranding=1&fs=1"></param>
					  <param name="allowScriptAccess" value="always"></param>
					  <param name="allowFullScreen" value="true"></param>
					  <embed src="http://www.youtube.com/v/'.$episode['enotelms:YouTubeUID'].'?version=3&autohide=1&showinfo=0&modestbranding=1&fs=1"
					         type="application/x-shockwave-flash"
					         allowscriptaccess="always"
							 allowfullscreen="true"
					         width="480" height="270"></embed>
					</object></div>';
				} else {
					$outputHTML .= "<div style='color: #aaaaaa'>".$this->pi_getLL('noYoutube')."</div>";
				}
				return $outputHTML;
			} else {
				return false;
			}
		}
	}
	function getUrl($string) {
		if(preg_match("/^http/",$string)) {
			return $string;
		} else {
			return t3lib_div::getIndpEnv('TYPO3_SITE_URL').substr($string,strlen(PATH_site));
		}
	}
	function podcastFeed()	{
	    //Get path to podcast directory
		$thePath = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['path']['vDEF'];
		//Check if the path is within fileadmin/
		if(!(t3lib_div::validPathStr($thePath) && strpos($thePath, 'fileadmin/') == 0)) {
			return ('Error: Invalid path to podcast directory');
		}
		//Get list of podcast files
		list($data,$order,$podcast,$metadata) = $this->getPodcastData($thePath);

		$onlyPublished = $this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['showOnlyPublishedInDefaultFeed']['vDEF'];
		
		if ($this->piVars['rss'] || isset($this->piVars['additionalRSSkey']))	{
			if ($this->piVars['rss'])	{$this->piVars['additionalRSSkey']='1';}
			$this->RSSFeed($data, $order, $podcast, $onlyPublished); // Output RSS feed
		} elseif($this->piVars['data'] && $this->podcastLocal) {
			$this->jsonData($data);
		} else { //Output podcast list as HTML

			//Parse through $podcast['feeds']
			$outputHTML='';
			foreach($podcast['feeds'] as $label => $options) {
				$additionalRSSkey = $label;
				$titleSuffix = (strlen($podcast['feeds'][$label]['label'])>0 ? '('.$podcast['feeds'][$label]['label'].')':'');
				$outputHTML.= ($outputHTML?' - ':'').$this->pi_linkTP('RSS '.$titleSuffix,Array($this->prefixId=>((string)$additionalRSSkey!=="1"?array('additionalRSSkey'=>$additionalRSSkey):array('rss'=>1))),1);
				$outputHTML.= ' - <a href="' . htmlspecialchars('itpc' . substr(t3lib_div::getIndpEnv('TYPO3_SITE_URL'),4) . $this->cObj->lastTypoLinkUrl).'">' . $this->pi_getLL('subscribeItunes') . " " .$titleSuffix.'</a>';
//				debug(array($outputHTML));
			}
			$totalSpilletidSeconds = 0;	
			if ($this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['showReverseOrder']['vDEF'] == true)
				$order = array_reverse($order);
			// Traverse them, compile item list:
			foreach($order as $idx)	{
				if (!isset($data[$idx]['category']) || !$data[$idx]['category']) {
					$category = '';
				} else {
					$category = $data[$idx]['category'];
				}
				
				if (!$onlyPublished || $data[$idx]['pubDate']<time()) {
					$downloadLinks = array();
					$resolutions = $podcast;
					$spilletid = '';
					//Go through video resolutions, generate links if file is present
					foreach($resolutions['feeds'] as $label => $options)	{
						$resolution = $options['version'];
						if (isset($data[$idx]['media'][$resolution]))	{
							//File with given resolution exists, and have not been written to the last ten seconds (upload)
							if (isset($data[$idx]['media'][$resolution])  && $data[$idx]['media'][$resolution]['_lastWrite']>10)	{

								$videourl = $this->getUrl($data[$idx]['media'][$resolution]['filename']);
								$downloadLinks[]='<td><p><a href="'.htmlspecialchars($videourl).'">'.$resolution.' ('.t3lib_div::formatSize($data[$idx]['media'][$resolution]['filesize']).')</a></p></td>';
								$spilletid = $data[$idx]['media'][$resolution]['duration'];
							} else {
								$downloadLinks[]='<td><p>'.$resolution.' <em>(Ved at blive uploaded)</em></p></td>';
							}
						} else { // File doesnt exist, display dummy label
							$downloadLinks[]='<td><p>'.$resolution.'</p></td>';
						}
					}
					
					//Compute the playtime in seconds for the entry
					$spilletidSplit = explode(':',$spilletid);
					$spilletidSeconds = -1;
					
					if (count($spilletidSplit)==3)	{
						$spilletidSeconds = $spilletidSplit[0]*60*60 + $spilletidSplit[1]*60 + $spilletidSplit[2];
					} elseif (count($spilletidSplit)==2)	{
						$spilletidSeconds = $spilletidSplit[0]*60 + $spilletidSplit[1];
					} elseif (count($spilletidSplit)==1)	{
						$spilletidSeconds = $spilletidSplit[0];
					}
					//Add playtime to total
					$totalSpilletidSeconds+=$spilletidSeconds;
					
					// Check for youtube link
					if ($data[$idx]['enotelms:YouTubeUID'])	{
						$downloadLinks[]='<td><p><a href="http://www.youtube.com/watch?v='.$data[$idx]['enotelms:YouTubeUID'].'"><img src="'.t3lib_extMgm::siteRelPath('enotepodcast').'icons/youtube.png" width="38" height="15" /></a></p></td>';
					}

					$attachments='';
					if (is_array($data[$idx]['attachment']) && count($data[$idx]['attachment']))	{
						$attachments = '<hr>';
						foreach($data[$idx]['attachment'] as $att)	{
							$attachments.='<p>Download: <b><a href="'.htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL').substr($att[0],strlen(PATH_site))).'">'.htmlspecialchars($att[2]).' ('.t3lib_div::formatSize($att[3]).')</a></b></p>';
						}
					}					
					
					$thumbnailWidth = intval($this->cObj->data['pi_flexform']['data']['sDEF']['lDEF']['thumbnailSize']['vDEF']);
					if ($thumbnailWidth<=0)	{$thumbnailWidth = 135;}
					
					//Generate HTML output for the current entry
					$out[$category] .='<tr>
						<td valign="top">'.$this->getImage($data[$idx]['media']['thumbnail'],0,$thumbnailWidth).'</td>
						<td valign="top">
						<h2>'.$this->pi_linkTP(htmlspecialchars(trim($data[$idx]['title'])),array($this->prefixId=>array('showDetails'=>htmlspecialchars(trim($data[$idx]['title'])))),1).'</h2>
						<p>'.substr(htmlspecialchars(trim($data[$idx]['description'])),0,200).(strlen(htmlspecialchars(trim($data[$idx]['description']))) > 200 ? " ".$this->pi_linkTP('[...]',array($this->prefixId=>array('showDetails'=>htmlspecialchars(trim($data[$idx]['title'])))),1) : '').'</p>
						<p><strong>'.$this->pi_getLL('date').':</strong> '.htmlspecialchars(strftime('%d/%m/%Y %R',$data[$idx]['pubDate'])).' - <strong>'.$this->pi_getLL('duration').':</strong> '.htmlspecialchars(trim($spilletid)).'</p>
							'.$attachments.'
						</td>
						<td valign="top">
							<table border="1" cellpadding="0" cellspacing="0" style="padding: 0 0 0 0 px; width:100%; background-color: #eeeeee; white-space: nowrap; "><tr>'.implode('</tr><tr>',$downloadLinks).'</tr></table>
						</td>
					</tr>';
				}
			}
			
			//Compute total playtime
			$HH=floor($totalSpilletidSeconds/60/60);
			$rem = $totalSpilletidSeconds-$HH*60*60;
			$MM=floor($rem/60);
			$SS = $rem-$MM*60;
			$totalTimeFormatted = $HH.':'.str_pad($MM, 2, "0", STR_PAD_LEFT).':'.str_pad($SS, 2, "0", STR_PAD_LEFT);
			
			ksort($out);
			// Combine html from categories into $outputHTML
			foreach ($out as $cat => $output) {
				if($cat != '')
					$outputHTML .= "<h3>".$cat."</h3><table border='0'>" . $output . "</table>";
			}
			// Add the label "Videos without category" only if there are other categories too
			if (sizeof($out) > 1)
				$outputHTML .= "<h3>" . $this->pi_getLL('videosWithoutCategory') . "</h3>";
			$outputHTML .= "<table border='0'>".$out['']."</table>";
			return $outputHTML.$this->pi_getLL('totalDuration').': '.$totalTimeFormatted;
		}
	}	
	
	//Gets image thumbnail
	/*function getImage($relFilename,$TSconf,$urlOnly=false)	{
		if($this->podcastLocal) {
			$TSconf['file'] = $relFilename;
			$ref = $this->cObj->IMG_RESOURCE($TSconf);
			$img = $this->cObj->IMAGE($TSconf);
			return ($urlOnly?$ref:$img);
		} else {

		}
	}*/

	function getImage($thumbnail, $num, $width, $urlOnly = false) {
		if($this->podcastLocal) {
			if(is_array($thumbnail)) {
				$path = substr($thumbnail[$num],strlen(PATH_site));
			} elseif($thumbnail) {
				$path = $thumbnail;
			} else {
				$path = t3lib_extMgm::siteRelPath('enotepodcast').'icons/defaulticon.png';
			}
			$TSconf = array('file' => $path,'file.'=>array('width'=>($width), 'ext'=>'jpg'));
			if($urlOnly) {
				return $this->cObj->IMG_RESOURCE($TSconf);
			} else {
				return $this->cObj->IMAGE($TSconf);
			}
		} else {
			if(is_array($thumbnail[$num])) {
				if(array_key_exists($width, $thumbnail[$num])) {
					$url = $thumbnail[$num][$width];
				} else {
					$url = array_shift($thumbnail[$num]);
				}
				return "<img src='" . $url . "' width='".$width."'></img>";
			}
		}
	}
	
	/** 
	 * Input: path relative to site; where podcast data should be read from (recursively)
	 */
	function getPodcastData($path)	{
		if($this->podcastLocal == false) {
			$data = file_get_contents($this->podcastUrl . "?tx_enotepodcast_pi1[data]=true");
			if($data){
				if($data = json_decode($data,true)) {
					$feeds = $data['feeds'];
					$episodes = $data['episodes'];
					$this->metadata = $data['metadata'];
					$order = array();
					foreach($episodes as $element) {
						$order[] = $element['pubDate'];
					}
					arsort($order);
					return Array($episodes,array_keys($order),array("feeds" => $feeds));
				}
			}
			return false;
		} else {
			$path = $this->podcastPath;
			// Set abs path:
			$absPath = t3lib_div::getFileAbsFileName($path);

			// Read all txt files (with meta data in) + then all media files supported by this extension.
			$fileArr = t3lib_div::getAllFilesAndFoldersInPath(array(),$absPath,'txt');
			$fileMedia = t3lib_div::getAllFilesAndFoldersInPath(array(),$absPath,'mp4,m4v,mov,png,jpg');
			$filesAll = t3lib_div::getAllFilesAndFoldersInPath(array(),$absPath);	// For attachments...ß
			
			// Find max filemtime in order to check for cached information... (does NOT count in attachments at this time!)
			$filemtimes=array();
			foreach($fileArr as $fileName)	{
				$filemtimes[]=filemtime($fileName).$fileName;
			}
			foreach($fileMedia as $fileName)	{
				$filemtimes[]=filemtime($fileName).$fileName;
			}

			$maxFileMTimeHash = md5(serialize($filemtimes));
			$cacheFileName = PATH_site.'typo3temp/tx_enotepodcast_cache/dir-'.md5($path).'.ser';
			list($returnVar,$cacheFileMtimeHash) = unserialize(t3lib_div::getUrl($cacheFileName));
			// If not cached, get stuff:
			$versions = $this->getVersions();
			if ($cacheFileMtimeHash != $maxFileMTimeHash)	{
				$fileArrMedia = array_flip($fileMedia);
				$data=array();
				$order=array();
				
				// Traverse meta files:
				foreach($fileArr as $fileAbsPath)	{
					$fileContent = t3lib_div::getUrl($fileAbsPath);	
					// Parse meta data configuration file and do some processing:
					$lines = t3lib_div::trimExplode(chr(10),$fileContent,1);
					$info = array();
					foreach($lines as $linecontent)	{
						// Make sure string is *strictly* valid UTF-8 (argument 'true')
						mb_detect_encoding($linecontent,'UTF-8',true) == "UTF-8" ? '' : $linecontent = utf8_encode($linecontent);
						if (substr($linecontent,0,1)!="#")	{
							list($fieldname, $value) = preg_split('/=/',$linecontent,2);
							$value = trim($value);
							$fieldname = trim($fieldname);
							if ($fieldname && $value && substr($value,0,1)!='[')	{
								$info[$fieldname] = $value;
							}
						}
					}
					if (!$info['title'])	{$info['title']='! NO TITLE !';}
					if ($info['pubDate'])	{
						$info['pubDate'] = mktime(substr($info['pubDate'],11,2), substr($info['pubDate'],14,2), 0, substr($info['pubDate'],5,2), substr($info['pubDate'],8,2) , substr($info['pubDate'],0,4));
					} else {
						$info['pubDate'] = filemtime($fileAbsPath);
					}
					// $info['pubDate'] = strftime('%G %m %e %R',$info['pubDate']);
					// Look up media:
					$baseAbsFileName = substr($fileAbsPath,0,-4);
					$extensions = array('mp4','mov','m4v');
					
									foreach($filesAll as $filename)	{
										if (t3lib_div::isFirstPartOfStr($filename,$baseAbsFileName.'-AT-'))	{
											$theAttachmentTitle = substr(basename($filename),strlen(basename($baseAbsFileName).'-AT-'));
											$theAttachmentTitle = preg_replace('/([A-Z])/',' $1',$theAttachmentTitle);
											$theAttachmentTitle = trim(preg_replace('/(-)/',' $1',$theAttachmentTitle));
											
											$info['attachment'][] = array($filename,basename($filename),$theAttachmentTitle,filesize($filename));
										}
									}
					
					//Go through all defined versions
					foreach($versions['feeds'] as $label => $options)	{
						$resolution = $options['version'];
						foreach($extensions as $extension)	{
							$testName = $baseAbsFileName.'-'.$resolution.'.'.$extension;
							if ($fileArrMedia[$testName])	{
								// Load from cache:
								$fileMTime_local = filemtime($testName);
								$cacheFileName_local = PATH_site.'typo3temp/tx_enotepodcast_cache/file-'.md5($testName).'.ser';
								list($info['media'][$resolution],$cacheFileMtime_local) = unserialize(t3lib_div::getUrl($cacheFileName_local));
								
								if ($fileMTime_local != $cacheFileMtime_local)	{
									//echo ("Creates metadata for ".$testName.'<br/>');
									$info['media'][$resolution] = array('filename'=>$testName, 'extension'=>$extension, 'filesize'=>filesize($testName), '_lastWrite'=>time()-$fileMTime_local);	// If last write is less than a few seconds it is currently being uploaded and should not be linked to...
									
									// Only read meta data if file is not being written to
									if ($info['media'][$resolution]['_lastWrite']>10)	{
										# Meta data:
										$cmd = '/usr/bin/exiftool "'.$testName.'"';
										$res = array();
										$meta = array();
										exec($cmd,$res);
										foreach($res as $line)	{
											list($fieldname, $value) = preg_split('/:/',$line,2);
											$value = trim($value);
											$fieldname = trim($fieldname);
											if ($fieldname && $value)	{
												$meta[$fieldname] = $value;
											}
										}
										$info['media'][$resolution]['mimetype']=$meta['MIME Type'];
										$info['media'][$resolution]['duration']=$meta['Duration'];
										$info['media'][$resolution]['avgBitrate']=$meta['Avg Bitrate'];
										$info['media'][$resolution]['imageWidth']=$meta['Image Width'];
										$info['media'][$resolution]['imageHeight']=$meta['Image Height'];

										t3lib_div::writeFileToTypo3tempDir($cacheFileName_local,serialize(array($info['media'][$resolution],$fileMTime_local)));		
									}
								} else {
									// debug('Was cached: '.$testName);
								}
								break;
							}
						}
					}

					// Look up thumbnails:
					for($a=0;$a<3;$a++)	{
						$testName = $baseAbsFileName.'-'.($a+1);
						if ($fileArrMedia[$testName.'.png'])	{
							$info['media']['thumbnail'][$a] = $testName.'.png';
						} elseif ($fileArrMedia[$testName.'.jpg'])	{
								$info['media']['thumbnail'][$a] = $testName.'.jpg';
						} else {
							break;
						}
					}
				
					$data[] = $info;
					$order[] = $info['pubDate'];
				}
				arsort($order);
				$returnVar = array($data,array_keys($order),$versions, $metadata);
					// Write to cache:
				t3lib_div::writeFileToTypo3tempDir($cacheFileName,serialize(array($returnVar,$maxFileMTimeHash)));		
			} else {
				#debug('Was cached...');
			}
			
			return $returnVar;
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/enotepodcast/pi1/class.tx_enotepodcast_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/enotepodcast/pi1/class.tx_enotepodcast_pi1.php']);
}

?>
