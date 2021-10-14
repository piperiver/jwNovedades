<?php
error_reporting(E_ERROR);
// error_reporting(E_ALL);
ini_set("display_errors", 1);
include_once('vendor/autoload.php');

use Sendinblue\Mailin;

class Jw {

    protected $URL_ARTICLES = "https://www.jw.org/es/novedades/";
    protected $CLASS_BODY_ARTICLE = 'syn-body';
    protected $URL_JW = 'https://www.jw.org/';

    protected $URL_TOKEN = 'https://b.jw-cdn.org/tokens/jworg.jwt';
    protected $API_VIDEOS = 'https://b.jw-cdn.org/apis/mediator/v1/categories/S/LatestVideos?detailed=1&clientType=www';
    protected $MODEL_URL_VIDEO = 'https://www.jw.org/finder?locale=es&category=LatestVideos&item=%KEY%&docid=1011214&applanguage=S';

    protected $fileWatched = 'watched.json';

    function getPageContent($url)
	{
		try {
			$infoApi = file_get_contents($url);
			return $infoApi;
		} catch (Exception $e) {
			return false;
		}
	}

    function getArticlesHtml(){
        $html = $this->getPageContent($this->URL_ARTICLES);
        $dom = new DomDocument();
        $dom->loadHTML($html);
        $finder = new DomXPath($dom);
        $articles = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $this->CLASS_BODY_ARTICLE ')]");

        $watched = $this->getWatched();
        $isNewWatched = false;
        $htmlArticlesComplete = '';
        foreach($articles as $article){
            if(in_array($article->nodeValue, $watched['articles'])){
                continue;
            }
            $isNewWatched = true;
            $watched['articles'][] = $article->nodeValue;
            $htmlArticle = $dom->saveHTML($article);
            $htmlArticle = str_replace('href="/es/', 'href="'.$this->URL_JW.'/es/', $htmlArticle);
            $htmlArticle = str_replace('/">', '/" target="_blank" >', $htmlArticle);

            $htmlArticlesComplete .= $htmlArticle;
        }

        if($isNewWatched){
            $this->setWatched(json_encode($watched));
            return $htmlArticlesComplete;
        }else{
            return false;
        }
    }

    function setWatched($content){
        return file_put_contents($this->fileWatched, $content);
    }

    function getWatched(){
        $content = file_get_contents($this->fileWatched, true);
        $content = $content === false ? [] : json_decode($content, true);
        $content = !is_array($content) ? [] : $content;
        if(!isset($content['articles'])){
            $content['articles'] = [];
        }
        if(!isset($content['videos'])){
            $content['videos'] = [];
        }
        return $content;
    }

    function getToken(){
        return $this->getPageContent($this->URL_TOKEN);
    }

    function getVideosData(){
        $token = $this->getToken();

        try {
            $ch = curl_init($this->API_VIDEOS);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'authorization: Bearer '.$token
            ));
            $output = curl_exec($ch);
            curl_close($ch);
            return json_decode($output, true);
        } catch (\Exception $th) {
            return false;
        }

    }

    function generateBodyVideos(){
        $dataVideos = $this->getVideosData();
        $title = $dataVideos['category']['description'];

        $watched = $this->getWatched();
        $isNewWatched = false;

        $htmlVideos = '<h1>'.$title.'</h1>';
        foreach($dataVideos['category']['media'] as $media){
            if(in_array($media['languageAgnosticNaturalKey'], $watched['videos'])){
                continue;
            }
            $isNewWatched = true;
            $watched['videos'][] = $media['languageAgnosticNaturalKey'];

            $duration = $media['durationFormattedMinSec'];
            $dataPublication = date('Y-m-d', strtotime($media['firstPublished']));
            $portada = $media['images']['wss']['sm'];
            $titleVideo = $media['title'];

            $url = str_replace('%KEY%', $media['languageAgnosticNaturalKey'], $this->MODEL_URL_VIDEO);

            $htmlVideos .= '
                <div class="video syn-body">
                    <div class="content-img">
                        <a href="'.$url.'" target="_blank">
                            <img src="'.$portada.'" loading="lazy" alt="..." class="img" />
                        </a>
                    </div>
                    <div class="description">
                        <a href="'.$url.'" target="_blank">
                            <p class="title">'.$titleVideo.'</p>
                        </a>
                        <p class="date">'.$dataPublication.'</p>
                        <p><span class="time">'.$duration.'</span></p>
                    </div>
                </div>

            ';
        }

        if($isNewWatched){
            $this->setWatched(json_encode($watched));
            return $htmlVideos;
        }else{
            return false;
        }
    }

    function styles(){
        return '
        <style>
            .containerInformation{
                font-family: Helvetica, sans-serif;
                font-size: 14px;
            }

            h1{
                text-align: center;
                margin-top: 50px;
            }

            .video{
                display: flex;
                flex-direction: row;
                justify-content: flex-start;
                align-items: center;
            }

            .content-img{
                position: relative;
            }

            .content-img img{
                border-radius: 5px;
                width: 250px;
                height: 100%;
                object-fit: cover;
            }

            .content-img a {
                display: block;
                height: 100%;
            }

            .time{
                background: rgba(0, 0, 0, 0.5);
                color: #fff;
                padding: 5px;
                border-radius: 5px;
            }

            .description {
                padding: 15px;
            }

            .description .title{
                color: #4a6da7;
                font-size: 16px;
                font-weight: 600;
            }

            .description .date{
                font-size: 14px;
                color: #858585;
            }

            .syn-body{
                padding: 15px;
                margin: 15px auto;
                border: 1px solid #d9d9d9;
                border-radius: 10px;
                box-shadow: 2px 2px 5px 2px #f2f2f2;
                max-width: 700px;
            }

            .pubDate{
                text-align: right;
                color: #858585;
                font-size: 14px;
            }

            .contextTitle{
                font-size: 14px;
                margin: 0;
            }

            .syn-body h3{
                margin: 10px 0;
            }

            .syn-body a{
                text-decoration: none;
                font-size: 16px;
                margin: 0;
                color: #4a6da7;
            }

            .desc{
                font-size: 12px;
                margin: 10px 0;
            }

        </style>
        ';
    }

    function content($children){
        $styles = $this->styles();
        return '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                '.$styles.'
                <title>Novedades JW</title>
            </head>
            <body>
                <div class="containerInformation">
                    '.$children.'
                </div>
            </body>
            </html>
        ';
    }

    function main(){
        $articles = $this->getArticlesHtml();
        $videos = $this->generateBodyVideos();

        if($articles === false && $videos === false){
            return false;
        }

        $contentArticles = $articles ? '
                <div>
                    <h1>Vea los últimos articulos</h1>
                    '.$articles.'
                </div>
            '
        :
            '';

        $contentVideos = $videos ? '
                <div>
                    '.$videos.'
                </div>
            '
        :
            '';

        return $this->content($contentArticles.$contentVideos);
    }

    public function sendMail($message, $asunto)
	{
        $api_key = 'tWEFr7Pm2sCDavAT';
        $from_email = 'prograymer@gmail.com';
        $from_name = 'Novedades';

        $to_email = 'piperiver7@gmail.com';
        $to_name = 'Novedades';
        $subject = 'Boletín de noticias JW';
        $mailin = new Mailin('https://api.sendinblue.com/v2.0',$api_key);
        $data = array(
            "to" => array($to_email=>$to_name),
            "from" => array($from_email,$from_name),
            "subject" => $subject,
            "html" => $message,
            "attachment" => array()
        );
        $response = $mailin->send_email($data);
        if(isset($response['code']) && $response['code']=='success'){
            echo 'Enviado';
        }else{
            echo 'Error';
        }
	}

}

date_default_timezone_set('America/Bogota');
if(isset($_GET['send']) && $_GET['send'] === 'true'){
    $jw = new Jw();
    $html = $jw->main();
    if($html){
        $jw->sendMail($html, 'JW NOVEDADES - LO NUEVO');
        file_put_contents('log.fff', date('Y-m-d H:i:s').' => Ejecutado con exito y correo enviado'.PHP_EOL, FILE_APPEND);
    }else{
        file_put_contents('log.fff', date('Y-m-d H:i:s').' => Nada nuevo. Correo no enviado'.PHP_EOL, FILE_APPEND);
    }
}else{
    file_put_contents('log.fff', date('Y-m-d H:i:s').' => Me ejecutaron sin el parametro'.PHP_EOL, FILE_APPEND);
}


