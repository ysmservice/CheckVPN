<?php
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
}

class VPNCheckLogger {
    public static function onRecentChangeSave( RecentChange $recentChange ) {
        $userIP = $recentChange->getPerformer()->getName();
        $vpnStatus = self::checkVPN($userIP);

        // リビジョンIDを取得
        $revisionId = $recentChange->getRevision()->getId();

        // IPアドレス、VPNステータス、リビジョンIDをデータベースに保存
        self::logIP($userIP, $vpnStatus, $revisionId);

        return true;
    }

    public static function onSkinTemplateOutputPageBeforeExec( &$skin, &$template ) {
        // 編集履歴における各エントリのリビジョンIDに基づいてVPNステータスを取得
        $revisionId = $skin->getContext()->getTitle()->getLatestRevID();
        $vpnStatus = self::getVPNStatusByRevisionId($revisionId);

        // VPN情報をユーザーツールリンク横に表示する
        if ($vpnStatus) {
            $template->data['usertoollinks'] .= Html::rawElement('span', ['class' => 'vpn-status'], "（VPN: $vpnStatus）");
        }

        return true;
    }

    private static function checkVPN( $ip ) {
        $url = "https://spur.us/context/" . urlencode( $ip );
        $response = file_get_contents($url);

        if ($response === false) {
            return "Unknown"; // エラー時は「Unknown」として扱う
        }

        // VPNかどうかを判定し、VPNサービス名を返す
        if (preg_match('/<span>\s*(.*?)VPN/i', $response, $matches)) {
            return $matches[1] . " VPN"; // VPNサービス名を返す
        }
        return "Not VPN";
    }

    private static function logIP($ip, $vpnStatus, $revisionId) {
        $dbw = wfGetDB( DB_PRIMARY );
        $dbw->insert(
            'vpn_ip_log', // テーブル名
            [
                'ip_address' => $ip,
                'vpn_status' => $vpnStatus,
                'revision_id' => $revisionId,
                'timestamp' => wfTimestampNow()
            ],
            __METHOD__
        );
    }

    private static function getVPNStatusByRevisionId($revisionId) {
        $dbr = wfGetDB( DB_REPLICA );
        $row = $dbr->selectRow(
            'vpn_ip_log',
            'vpn_status',
            [ 'revision_id' => $revisionId ],
            __METHOD__
        );

        if ($row) {
            return $row->vpn_status;
        }
        return "Unknown";
    }

    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        $updater->addExtensionTable( 'vpn_ip_log', __DIR__ . '/vpn_ip_log.sql' );
    }
}
