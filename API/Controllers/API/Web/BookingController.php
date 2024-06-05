<?php

namespace App\Http\Controllers\API\Web;

use App\Helpers\Response\SendResponse;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Place;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
//    müşterilerin status durumlarını gösteren api
    public function client(Request $request)
    {

        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [

                  //  'place_id' => 'required|exists:booking,id',
                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }

        $bookings = Booking::select('day',
            DB::raw("CASE status
                        WHEN '0' THEN 'bekliyor'
                        WHEN '1' THEN 'onaylandı'
                        WHEN '2' THEN 'reddedildi'
                        ELSE 'bilinmeyen'
                    END as status_text"),
            DB::raw('count(*) as count'))
            ->where('place_id', $request->place_id)
            ->groupBy('day', 'status')
            ->get();

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }


// dışarıdan place_id alınmadan bütün placelere ait verileri getirir
//        $bookings = Booking::select('place_id', 'day',
//            DB::raw("CASE status
//                        WHEN '0' THEN 'bekliyor'
//                        WHEN '1' THEN 'onaylandı'
//                        WHEN '2' THEN 'reddedildi'
//                        ELSE 'bilinmeyen'
//                    END as status_text"),
//            DB::raw('count(*) as count'))
//            ->groupBy('place_id', 'day', 'status')
//            ->get();

        return SendResponse::sendResponse($bookings, "client status Successfully!");
    }


//mekana rezervasyon yapmış müşterilerin listesi ve tarihi
    public function clients(Request $request){

        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [

                   // 'place_id' => 'required|exists:booking,id',
                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }

        $bookings = DB::table('booking')
            ->join('users', 'booking.user_id', '=', 'users.id')
            ->select('booking.day', 'booking.person_number', 'users.name','users.surname')
            ->where('booking.place_id', $request->place_id)
            ->get();

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

        return SendResponse::sendResponse($bookings, "clients Successfully!");



    }


    public function clientDet(Request $request){
        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [

                   // 'place_id' => 'required|exists:booking,id',
                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }
// müşteri detay
            $bookings = Booking::where('place_id', $request->place_id)->get();

            $user_ids = [];

            foreach ($bookings as $booking) {
                $user_ids[] = $booking->user_id;
            }

            $users = User::whereIn('id', $user_ids)
                ->select('name', 'surname', 'phone','email')
                ->get();




        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

        return SendResponse::sendResponse($users, "client detail Successfully!");



    }




    public function detail(Request $request)
    {

        try {
            $bookingId = $request->booking_id; // Dışarıdan gelen booking_id
            $token = $request->input('token');

            $user = User::where('token', $token)->first();

            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;

            $booking = Booking::join('users', 'booking.user_id', '=', 'users.id')
                ->where('booking.id', $bookingId)
                ->where('booking.place_id', $placeId) // Kontrol: place_id ile eşleşme
                ->select(
                    'booking.day',
                    'booking.time',
                    'booking.person_number',
                    'booking.description',
                    'users.name',
                    'users.surname'
                )
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Belirtilen booking_id veya place_id ile eşleşen rezervasyon bulunamadı.'], 404);
            }

            return response()->json(['data' => $booking], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }


    public function delete(Request $request)
    {

        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [
                    'token' => 'required|exists:users,token',
                    'id' => 'required|exists:booking,id',

                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }

            #Delete
            $user = User::where('token', $request->token)->first();
            if ($user) {
                $booking = Booking::where('id', $request->id)->first()->delete();
                if ($booking) {
                    return SendResponse::sendResponse($booking, "Booking Delete Successfully!");
                }
            }


        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }



    public function list(Request $request)
    {
        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [
                   'token' => 'required|exists:users,token',

                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }
// yıllık veriler
//onaylanan veriler burdan başlıyor
            $token = $request->input('token');

            $user = User::where('token', $token)->first();

            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;

            $bookings = DB::table('booking')
                ->select(DB::raw('MONTH(day) AS ay, COUNT(*) AS onaylanan_sayisi'))
                ->where('place_id', $placeId)
                ->where('status', 1)
                ->groupBy(DB::raw('MONTH(day)'))
                ->orderBy(DB::raw('MONTH(day)'))
                ->get();

            $counts = [];
            for ($i = 1; $i <= 12; $i++) {
                $counts[] = 0;
            }

            foreach ($bookings as $booking) {
                $month = $booking->ay;
                $count = $booking->onaylanan_sayisi;
                $counts[$month - 1] = $count;
                // onaylanan veriler bitti
            }
            //buradan sonrası reddedilen veriler
            $bookings = DB::table('booking')
                ->select(DB::raw('MONTH(day) AS ay, COUNT(*) AS reddedilen_sayisi'))
                ->where('place_id', $placeId)
                ->where('status', 2)
                ->groupBy(DB::raw('MONTH(day)'))
                ->orderBy(DB::raw('MONTH(day)'))
                ->get();

            $countsred = [];
            for ($i = 1; $i <= 12; $i++) {
                $countsred[] = 0;
            }

            foreach ($bookings as $booking) {
                $month = $booking->ay;
                $count = $booking->reddedilen_sayisi;
                $countsred[$month - 1] = $count;
            }

            return response()->json([
                'data' => [
                    'approved' => $counts,
                    'rejected' => $countsred
                ],
                'message' => 'Booking List Successfully!'
            ]);
//                return SendResponse::sendResponse([$counts,$countsred],"Booking List Successfully!");



        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }

    public function total(Request $request){
        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [
                     'token' => 'required|exists:users,token',

                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }

// rezervasyonların toplam bekleyen beklenen seklinde ayrı ayrı toplamları
            $token = $request->input('token');

            $user = User::where('token', $token)->first();

            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;

        $total=Booking::where('place_id',$placeId)->count();
        $approved=Booking::where('place_id',$placeId)->where('status',1)->count();
        $rejected=Booking::where('place_id',$placeId)->where('status',2)->count();
        $waiting=Booking::where('place_id',$placeId)->where('status',0)->count();
        $expected=Booking::where('place_id',$placeId)->where('status',3)->count();


        return response()->json([
            'data' => [
                'total' => $total,
                'approved' => $approved,
                'rejected' => $rejected,
                'waiting' => $waiting,
                'expected' => $expected

            ],
            'message' => 'Booking List Successfully!'
        ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function approve(Request $request)
    {

        try {
            $bookingId = $request->booking_id;

            $token = $request->input('token');
            $user = User::where('token', $token)->first();
            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;


            $approve = Booking::find($bookingId);


            if (!$approve || $approve->deleted_at) {
                return response()->json(['message' => 'Hatalı rezervasyon IDsi veya  silinmiş'], 400);
            }

            if ($approve->place_id != $placeId) {
                return response()->json(['message' => 'Hatalı place_id veya onaylama IDsi'], 400);
            }
            if ($approve->status != 1) {
                $approve->status=1;
            }
            else{

                return response()->json(['message' => 'Rezervasyon zaten onaylanmış'], 400);
            }

            $approve->save();

            return response()->json(['message' => 'rezervasyon onaylandı.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }

    public function statistics(Request $request){

//her ayın toplam rezervasyonu
        $token = $request->input('token');

        $user = User::where('token', $token)->first();

        $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
        $placeId = $placeid->place_id;

        $bookings = DB::table('booking')
            ->select(DB::raw('MONTH(day) AS ay, COUNT(*) AS rezervasyon_sayisi'))
            ->where('place_id', $placeId)
            ->groupBy(DB::raw('MONTH(day)'))
            ->orderBy(DB::raw('MONTH(day)'))
            ->get();

        $counts = [];
        for ($i = 1; $i <= 12; $i++) {
            $counts[] = 0;
        }

        foreach ($bookings as $booking) {
            $month = $booking->ay;
            $count = $booking->rezervasyon_sayisi;
            $counts[$month - 1] = $count;

        }
        return SendResponse::sendResponse($counts, "Total Statistics Successfully!");
    }

    public function week(Request $request){

        $token = $request->input('token');

        $user = User::where('token', $token)->first();

        $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
        $placeId = $placeid->place_id;

        $today = Carbon::today();
        $type = $request->type;

        //haftalık
        if ($type==0){
        $endDate = $today->copy()->subDays(6); // Geriye doğru 6 gün ekledik

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $endDate->copy()->addDays($i);
            $dates[] = $date->toDateString();
        }
//onaylanmış verilerin kısmı
        $bookings = Booking::select(DB::raw('DATE(day) as date'), DB::raw('COUNT(*) as count'))
            ->where('place_id', $placeId)
            ->where('status', 1)
            ->whereBetween(DB::raw('DATE(day)'), [$endDate->toDateString(), $today->toDateString()])
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        $result = [];
        foreach ($dates as $date) {
            $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
            $result[$date] = $count;
        }

        //red verilerin kısmı
        $bookings = Booking::select(DB::raw('DATE(day) as date'), DB::raw('COUNT(*) as count'))
            ->where('place_id', $placeId)
            ->where('status', 2)
            ->whereBetween(DB::raw('DATE(day)'), [$endDate->toDateString(), $today->toDateString()])
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        $resultred = [];
        foreach ($dates as $date) {
            $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
            $resultred[$date] = $count;
        }

        $totalApproved = array_sum($result);
        $totalRejected = array_sum($resultred);

        return response()->json([
            'data' => [
                'approved' => $result,
                'rejected' => $resultred,
                'total_approved' => $totalApproved,
                'total_rejected' => $totalRejected
            ],
            'message' => 'week statistic Successfully!'
        ]);

        //aylık
        }
        elseif ($type==1){
            $endDate = $today->copy()->subDays(27);
            $dates = [];
            for ($i = 0; $i < 28; $i++) {
                $date = $endDate->copy()->addDays($i);
                $dates[] = $date->toDateString();
            }

            $bookings = Booking::select(DB::raw('DATE(day) as date'), DB::raw('COUNT(*) as count'))
                ->where('place_id', $placeId)
                ->where('status', 1)
                ->whereBetween(DB::raw('DATE(day)'), [$endDate->toDateString(), $today->toDateString()])
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

// Tarihlerin hafta numarasına göre sıralanması
            usort($dates, function ($a, $b) {
                return strtotime($a) - strtotime($b);
            });

// Haftalık gruplar oluşturmak için
            $groupSize = 7;
            $totalDates = count($dates);
            $groupCount = ceil($totalDates / $groupSize);
            $groupedData = [];

            for ($i = 0; $i < $groupCount; $i++) {
                $startIndex = $i * $groupSize;
                $groupedDates = array_slice($dates, $startIndex, $groupSize);
                $groupTotal = 0;

                foreach ($groupedDates as $date) {
                    $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
                    $groupTotal += $count;
                }
                $groupedData[] = $groupTotal;

                $result = [];
                foreach ($dates as $date) {
                    $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
                    $result[$date] = $count;
                }
                // Tarihlerin listesi
                $dates = array_keys($result);
            }


            $dates = [];
            for ($i = 0; $i < 28; $i++) {
                $date = $endDate->copy()->addDays($i);
                $dates[] = $date->toDateString();
            }

            $bookings = Booking::select(DB::raw('DATE(day) as date'), DB::raw('COUNT(*) as count'))
                ->where('place_id', $placeId)
                ->where('status', 2)
                ->whereBetween(DB::raw('DATE(day)'), [$endDate->toDateString(), $today->toDateString()])
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

// Tarihlerin hafta numarasına göre sıralanması
            usort($dates, function ($a, $b) {
                return strtotime($a) - strtotime($b);
            });

// Haftalık gruplar oluşturmak için
            $groupSize = 7;
            $totalDates = count($dates);
            $groupCount = ceil($totalDates / $groupSize);
            $groupedDatared = [];

            for ($i = 0; $i < $groupCount; $i++) {
                $startIndex = $i * $groupSize;
                $groupedDates = array_slice($dates, $startIndex, $groupSize);
                $groupTotal = 0;

                foreach ($groupedDates as $date) {
                    $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
                    $groupTotal += $count;
                }
                $groupedDatared[] = $groupTotal;

                $resultred = [];
                foreach ($dates as $date) {
                    $count = isset($bookings[$date]) ? $bookings[$date]['count'] : 0;
                    $resultred[$date] = $count;
                }
            }


            $totalApproved = array_sum($result);
           $totalRejected = array_sum($resultred);

            return response()->json([
                'data' => [
                    'approved' => $groupedData,
                  'rejected' => $groupedDatared,
                  'total_approved' => $totalApproved,
                    'total_rejected' => $totalRejected
                ],
                'message' => 'monthly statistic Successfully!'
            ]);


        }
        // günlük
        elseif ($type==2){
            $currentDate = date('Y-m-d');

            $day_approved = DB::table('booking')
                ->where('place_id', $placeId)
                ->where('status',1)
                ->where('day', $currentDate)->count();

            $day_rejected = DB::table('booking')
                ->where('place_id', $placeId)
                ->where('status',2)
                ->where('day', $currentDate)->count();

            return response()->json([
                'data' => [
                    'day_approved' => $day_approved,
                    'day_rejected' => $day_rejected,

                ],
                'message' => 'day statistic Successfully!'
            ]);

        }

    }

    public function time(Request $request){
        $currentDate = date('Y-m-d');


        $token = $request->input('token');

        $user = User::where('token', $token)->first();

        $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
        $placeId = $placeid->place_id;

        $currentTime = $request->time;
        $type= $request->type;//type değerleri 0= bekleyen, 1=onaylandı, 2=reddedilen, 3=beklenen. eğer 4 değeri gelirse tamamını getir

        $bookingsQuery = DB::table('booking')
            ->join('users', 'booking.user_id', '=', 'users.id')
            ->select('booking.person_number', 'booking.day', 'booking.id', 'users.name', 'users.surname')
            ->where('place_id', $placeId)
            ->where('day', $currentDate)
            ->whereTime('time', '>=', $currentTime)
            ->whereTime('time', '<=', date('H:i:s', strtotime('+59 minutes', strtotime($currentTime))));

        if ($type != 4) {
            $bookingsQuery->where('status', $type);
        }

        $bookings = $bookingsQuery->get();

        return response()->json($bookings);

    }

    public function reject(Request $request)
    {

        try {
            $bookingId = $request->booking_id;

            $token = $request->input('token');

            $user = User::where('token', $token)->first();

            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;

            $reject_des = $request->reject_des;


            $approve = Booking::find($bookingId);


            if (!$approve || $approve->deleted_at) {
                return response()->json(['message' => 'Hatalı rezervasyon IDsi veya  silinmiş'], 400);
            }

            if ($approve->place_id != $placeId) {
                return response()->json(['message' => 'Hatalı place_id veya onaylama IDsi'], 400);
            }
            if ($approve->status != 2) {
                $approve->status=2;
            }
            else{

                return response()->json(['message' => 'Rezervasyon zaten onaylanmış'], 400);
            }
            $approve->reject_des = $reject_des;

            $approve->save();

            return response()->json(['message' => 'rezervasyon reddedildi.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }
    public function monthly(Request $request){

        $token = $request->input('token');

        $user = User::where('token', $token)->first();

        $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
        $placeId = $placeid->place_id;

        $date = $request->input('date'); // veya istediğin başka bir şekilde dışarıdan alabilirsin
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $bookingCounts = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $bookingCounts[] = [
                'date' => "$year-$month-$day",
                'waiting' => 0,
                'approved' => 0,
                'rejected' => 0,
                'expected' => 0,
            ];
        }


        $results = DB::table('booking')
            ->select(DB::raw('DAY(day) as day'), 'status', DB::raw('count(*) as count'))
            ->where('place_id', $placeId)
            ->whereYear('day', $year)
            ->whereMonth('day', $month)
            ->groupBy('day', 'status')
            ->get();


        foreach ($results as $result) {
            $statusLabel = $this->getStatusLabel($result->status); // Durum kodunu etikete dönüştürme
            $bookingCounts[$result->day - 1][$statusLabel] = $result->count;
        }

        return response()->json($bookingCounts);
    }

// Durum kodunu etikete çevirme fonksiyonu
    private function getStatusLabel($status)
    {
        switch ($status) {
            case 0: return 'waiting';
            case 1: return 'approved';
            case 2: return 'rejected';
            case 3: return 'expected';

        }



    }

    public function customers(Request $request){
        try {
            #Validation
            $validator = \Validator::make($request->all(),
                [
                    'token' => 'required|exists:users,token',
                    'first_result' => 'required',
                    'max_result' => 'required',
                ]);

            #Validation Errors Messages
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 401);
            }
            $token = $request->input('token');
            $user = User::where('token', $token)->first();
            $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
            $placeId = $placeid->place_id;
            $page = $request->input('first_result');
            $perPage = $request->input('max_result');
            $bookingsQuery = DB::table('booking')
                ->join('users', 'booking.user_id', '=', 'users.id')
                ->select('booking.person_number', 'booking.day', 'booking.id', 'users.name', 'users.surname')
                ->where('place_id', $placeId);

            $totalCount = $bookingsQuery->count();

            $bookings = $bookingsQuery->offset(($page - 1) * $perPage)->limit($perPage)->get();

            return response()->json([
                'data' => $bookings,
                'total_count' => $totalCount,
                'current_page' => $page,
                'per_page' => $perPage,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }

    }

    public function table(Request $request){

        $token = $request->input('token');

        $user = User::where('token', $token)->first();

        $placeid = DB::table('model_has_roles')->where('model_id', $user->id)->first();
        $placeId = $placeid->place_id;

        $today = Carbon::today();
        $startTime = Carbon::create($today->year, $today->month, $today->day, 10, 0, 0);
        $endTime = Carbon::create($today->year, $today->month, $today->day, 23, 0, 0);

        $place = Place::find($placeId);
        $numberTables = $place->number_tables;

        $response = [];

        $currentHour = clone $startTime;
        while ($currentHour->lte($endTime)) {
            $waitingCount = Booking::where('place_id', $placeId)
                ->whereIn('status', [0, 3])
                ->whereDate('day', $today)
                ->whereTime('time', '>=', $currentHour)
                ->whereTime('time', '<=', $currentHour->copy()->addHour())
                ->count();

            $confirmedCount = Booking::where('place_id', $placeId)
                ->where('status', 1)
                ->whereDate('day', $today)
                ->whereTime('time', '>=', $currentHour)
                ->whereTime('time', '<=', $currentHour->copy()->addHour())
                ->count();

            $emptyTablesCount = $numberTables - ($waitingCount + $confirmedCount);

            $response[] = [
                'hour_range' => $currentHour->format('H:i'),
                'waiting_count' => $waitingCount,
                'confirmed_count' => $confirmedCount,
                'empty_tables_count' => $emptyTablesCount,
            ];

            $currentHour->addHour();
        }

        return response()->json($response);


    }


}













