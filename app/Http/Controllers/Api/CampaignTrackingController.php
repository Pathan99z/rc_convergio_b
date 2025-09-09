<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class CampaignTrackingController extends Controller
{
    public function open(Request $request)
    {
        $rid = $request->query('rid');
        if (!$rid || !URL::hasValidSignature($request)) {
            return response('', 204);
        }
        $row = DB::table('campaign_recipients')->where('id', (int) $rid)->first();
        if ($row) {
            DB::table('campaigns')->where('id', $row->campaign_id)->increment('opened_count');
            DB::table('campaign_recipients')->where('id', $row->id)->update(['opened_at' => now()]);
        }
        $gif = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        return response($gif, 200)->header('Content-Type', 'image/gif');
    }

    public function click(Request $request)
    {
        $rid = $request->query('rid');
        $url = $request->query('u');
        if (!$rid || !$url || !URL::hasValidSignature($request)) {
            return redirect('/');
        }
        $row = DB::table('campaign_recipients')->where('id', (int) $rid)->first();
        if ($row) {
            DB::table('campaigns')->where('id', $row->campaign_id)->increment('clicked_count');
            DB::table('campaign_recipients')->where('id', $row->id)->update(['clicked_at' => now()]);
        }
        return redirect(urldecode($url));
    }
}


