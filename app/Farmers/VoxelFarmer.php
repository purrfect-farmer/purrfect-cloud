<?php
namespace App\Farmers;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;


class VoxelFarmer extends BaseFarmer
{

    protected $key = 'voxel';
    protected $origin = 'https://api.voxelplay.app';

    public function process()
    {
        try {
            $api = $this->getApi()->withResponseMiddleware(
                function (ResponseInterface $response) {
                    return $response
                        ->withBody(
                            Utils::streamFor(
                                $this->decompressText((string) $response->getBody())
                            )
                        )
                        ->withHeader('Content-Type', 'application/json');
                }
            );

            $initData = $this->farmer->getInitData();
            $user = $api->post(
                'https://api.voxelplay.app/voxel/user',
                ['initData' => $initData]
            )->json();

            /** Claim Inventory */
            $inventory = $api->post(
                'https://api.voxelplay.app/voxel/inventory',
                ['initData' => $initData]
            )->collect();
            $canClaim = $inventory->some(
                fn($item) => $item['farming'] && $item['timeToClaim'] === 0
            );

            if ($canClaim) {
                $api->post(
                    'https://api.voxelplay.app/voxel/inventory/claimall',
                    ['initData' => $initData]
                );
            }

            $missions = collect($user['configuration']['Missions'])->filter(
                fn($item) =>
                $item['Enabled'] &&
                $this->validateGroup($item) &&
                $this->validateReferrals($user['user'], $item) &&
                $this->validateAvailability($user['user'], $item) &&
                $this->validateTelegramTask($item['StartLink'] ?? null)
            );

            if ($missions->isNotEmpty()) {
                $mission = $missions->random();

                /** Join Telegram Link */
                $this->tryToJoinTelegramLink($mission['StartLink'] ?? null);

                /** Complete Task */
                $api->timeout(60)->post('https://api.voxelplay.app/voxel/mission-verify', [
                    'initData' => $initData,
                    'missionID' => $mission['ID']
                ]);
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);

            /** Disconnect Farmer */
            $this->disconnect();
        }
    }

    public function validateGroup($item)
    {
        return in_array($item['Group'], ["socials", "partners"]);
    }

    public function validateReferrals($user, $item)
    {
        return (
            $item['Group'] !== "friends" ||
            intval($item['Payload']) <= count($user['Referral']['Referrals'])
        );
    }

    public function validateAvailability($user, $item)
    {
        return !array_key_exists($item['ID'], $user['MissionsData']);
    }

    public function decodeResponse(string $textResponse)
    {
        $decompressedText = $this->decompressText($textResponse);

        $data = json_decode($decompressedText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON decode error: " . json_last_error_msg());
        }

        return $data;
    }

    public function decompressText(string $textResponse)
    {
        $compressedBytes = base64_decode($textResponse);
        $decompressedText = gzdecode($compressedBytes);
        if ($decompressedText === false) {
            throw new \Exception("Failed to decompress data");
        }
        return $decompressedText;
    }
}