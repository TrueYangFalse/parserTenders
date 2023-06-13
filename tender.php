<?php
require_once 'simple_html_dom.php';
require_once 'DB.php';

set_time_limit(1000);

function request($url, $postdata = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($postdata) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

$db = new DB();
$offset = 0;

do {
    $tenderNum = [];
    $tenderDate = [];
    $organizerName = [];

    $post = 'limit=10&offset='.$offset.'&total=7436&sortAsc=false&sortColumn=EntityNumber&MultiString=&__AllowedTenderConfigCodes=&IntervalRequestReceivingBeginDate.BeginDate=&IntervalRequestReceivingBeginDate.EndDate=&IntervalRequestReceivingEndDate.BeginDate=&IntervalRequestReceivingEndDate.EndDate=&IntervalBidReceivingBeginDate.BeginDate=&IntervalBidReceivingBeginDate.EndDate=&ClassifiersFieldData.SiteSectionType=bef4c544-ba45-49b9-8e91-85d9483ff2f6&ClassifiersFieldData.ClassifiersFieldData.__SECRET_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=&OrganizerData=';
    $jsonResponse = request('https://tender.rusal.ru/Tenders/Load', $post);

    $data = json_decode($jsonResponse, true);

    if (isset($data['Rows'])) {
        foreach ($data['Rows'] as $item) {
            if (isset($item['TenderNumber'])) {
                $tenderNum[] = $item['TenderNumber'];
                $tenderDate[] = $item['RequestReceivingBeginDate'];
                $organizerName[] = $item['OrganizerName'];
            }
        }
    }

    $tenders = [];

    foreach ($tenderNum as $key => $value) {
        $reUrl = 'https://tender.rusal.ru/Tender/*/1';
        $url = str_replace('*', $value, $reUrl);

        $postDoc = str_replace('*', $value, '/Tender/*/1');
        $jsonResponseDoc = request($url, $postDoc);

        $dataDoc = json_decode($jsonResponseDoc, true);
        $domDoc = str_get_html($jsonResponseDoc);

        $tender = [];

        $tender['TenderNumber'] = $value;
        $tender['URL'] = $url;
        $tender['Organization'] = $organizerName[$key] ?? ": Not found";
        $tender['RequestDate'] = date('Y-m-d H:i:s', strtotime($tenderDate[$key])) ?? ": Not found";

        $documents = [];

        $Doc = $domDoc->find('.file-download-link');

        foreach ($Doc as $element) {
            $name = trim($element->plaintext);
            $link = 'https://tender.rusal.ru' . $element->href;

            $documents[] = [
                'name' => $name,
                'link' => $link,
            ];
        }

        $tenders[] = $tender;

        $tenders[$key]['Document'] = $documents;
        $db->insertGame($tenders, $key);

        $domDoc->clear();
    }

    foreach ($tenders as $tender) {
        echo "Tender Number: " . $tender['TenderNumber'] . "<br>";
        echo "URL: <a href=\"" . $tender['URL'] . "\">" . $tender['URL'] . "</a><br>";
        echo "Organization: " . $tender['Organization'] . "<br>";
        echo "Request Date: " . $tender['RequestDate'] . "<br>";

        echo "Documents:<br>";
        if (empty($tender['Document'])) {
            echo "Отсутствует<br>";
        } else {
            foreach ($tender['Document'] as $document) {
                echo "<a href=\"" . $document['link'] . "\">" . $document['name'] . "</a><br>";
            }
        }

        echo "<br>";
    }

    $offset += 10;
} while (!empty($data['Rows']));
?>
