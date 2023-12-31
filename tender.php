<?php
require_once 'simple_html_dom.php';
require_once 'DB.php';
require_once 'CurlRequest.php';

set_time_limit(1000);



$db = new DB();
$curlRequest = new CurlRequest();
$offset = 0;

do {
    $tenderNum = [];
    $tenderDate = [];
    $organizerName = [];

    $post = 'limit=10&offset='.$offset.'&total=7436&sortAsc=false&sortColumn=EntityNumber&MultiString=&__AllowedTenderConfigCodes=&IntervalRequestReceivingBeginDate.BeginDate=&IntervalRequestReceivingBeginDate.EndDate=&IntervalRequestReceivingEndDate.BeginDate=&IntervalRequestReceivingEndDate.EndDate=&IntervalBidReceivingBeginDate.BeginDate=&IntervalBidReceivingBeginDate.EndDate=&ClassifiersFieldData.SiteSectionType=bef4c544-ba45-49b9-8e91-85d9483ff2f6&ClassifiersFieldData.ClassifiersFieldData.__SECRET_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=&OrganizerData=';
    $jsonResponse = $curlRequest->request('https://tender.rusal.ru/Tenders/Load', $post);

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
        $jsonResponseDoc = $curlRequest->request($url, $postDoc);

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
            $name = preg_replace('/\s+/', ' ', $element->plaintext);
            $link = 'https://tender.rusal.ru' . $element->href;

            $documents[] = [
                'name' => $name,
                'link' => $link,
            ];
        }

        $tenders[] = $tender;

        $tenders[$key]['Document'] = $documents;
        $db->insertTender($tenders, $key);

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
