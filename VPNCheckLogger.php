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

        // 編集履歴のコメントにVPNステータスを追加
        $summary = $recentChange->getComment() . " (IP: $userIP, VPN Status: $vpnStatus)";
        $recentChange->setComment($summary);

        return true;
    }

    private static function checkVPN( $ip ) {
        $url = "https://spur.us/context/" . urlencode( $ip );
        $response = file_get_contents($url);

        if ($response === false) {
            return "Unknown"; // エラー時は「Unknown」として扱う
        }

        // VPNかどうかを判定するための正規表現
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

    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        $updater->addExtensionTable( 'vpn_ip_log', __DIR__ . '/vpn_ip_log.sql' );
    }
}
