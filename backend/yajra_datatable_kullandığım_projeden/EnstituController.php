<?php

namespace App\Http\Controllers\panel\Enstitu;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;

use App\Http\Middleware\DanismanData;
use App\Models\Academician;
use App\Models\Announcement;
use App\Models\ApplicationDates;
use App\Models\AsilYedek;
use App\Models\Department;
use App\Models\Form;
use App\Models\Institute;
use App\Models\JoinForm;
use App\Models\MainDepartment;
use App\Models\Student;
use App\Models\User;
use App\Models\UserForm;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use function GuzzleHttp\Promise\all;

class EnstituController extends Controller
{
    public function index()
    {
        $institutes = Institute::all();
        $student= JoinForm::all()->count();
        $department= Department::where('is_active',1)->count();
        $academician= Academician::all()->count();
        return view('institute.index', compact('institutes','student','department','academician'));

    }

    public function student_index()
    {
        $institutes = Institute::all();
        return view('Enstitu.students', compact('institutes'));

    }

    public function student_fetch()
    {
        $student = Student::all();
        return DataTables::of($student)
            ->addColumn('detail', function ($data) {
                return "<button onclick='detailStudent(" . $data->id . ")' class='btn btn-info'>Detay</button>";
            })->addColumn('detailForm', function ($data) {
                return "<a  href='" . route('form-info', $data->id) . "' class='btn btn-success open-modal'>Formlar</a>";
            })->editColumn('adSoyad', function ($data) {
                $user = User::where('id', $data->user_id)->first();
                return $user->userFirstName . ' ' . $user->userLastName;
            })->editColumn('institute_id', function ($data) {
                $no = Student::where('ogrenci_no', $data->ogrenci_no)->first('main_department_id');
                $department = MainDepartment::where('id', $no->main_department_id)->first();
                if ($department->name) {
                    return $department->name;
                } else {
                    return "Kayıtlı Enstitü Bulunamadı";
                }
            })
            ->rawColumns(['detail', 'detailForm', 'institute_id'])
            ->make(true);
    }

    public function student_show(Request $request)
    {
        $students = Student::where('id', $request->id)->first();
        $institutes = Institute::all();

        return response()->json([]);

    }

    function student_get(Request $request)
    {

        return response()->json([
            'updateId' => $request->id,


        ]);
    }

    function student_detail(Request $request)
    {
        return response()->json(['Success' => 'success']);
    }

    function get(Request $request)
    {
        {


            $departments = Department::where('id', $request->id)->first();
            $students = Student::where('id', $request->id)->first();
            $users = User::where('id', $request->id)->first();

            return response()->json(['student_name' => $students->ogrenci_no, 'department' => $departments->name, 'email' => $users->userEMailAddress
            ]);

        }
    }

    function getForms($id)
    {

        $student = Student::where('id', $id)->first();

        $userForms = UserForm::where('user_id', $student->user_id)->where('sent_role', 4)->get();
        return DataTables::of($userForms)
            ->editColumn('form_name', function ($data) {
                return Form::where('id', $data->form_id)->first()->name;
            })->addColumn('feedback', function ($data) {
                if ($data->approval == 0) {
                    return "<button onclick='feedbackForm($data->id)' class='btn btn-warning'>Geri Bildirim</button>";
                } else {
                    return "<button disabled onclick='#' class='btn btn-warning'>Geri Bildirim</button>";
                }
            })
            ->addColumn('detail', function ($data) {
                return "<button onclick='detailForm($data->id)' class='btn btn-info'>Detay</button>";
            })
            ->addColumn('approve', function ($data) {
                if ($data->approval == 0) {
                    return "<button onclick='approveFormPost($data->id)' class='btn btn-success'>Onayla</button>";
                } else {
                    if ($data->approval == 1) {
                        return "<button disabled onclick='#' class='btn btn-success'>Onaylandı</button>";
                    }
                }
            })
            ->addColumn('decline', function ($data) {
                if ($data->approval == 0) {
                    return "<button onclick='declineFormPost($data->id)' class='btn btn-danger'>Reddet</button>";
                } else {
                    if ($data->approval == 2) {
                        return "<button disabled onclick='#' class='btn btn-danger'>Reddedildi</button>";
                    }
                }
            })
            ->rawColumns(['feedback', 'detail', 'approve', 'decline'])->make(true);
    }

    public function acceptForm(Request $request){
        $userForm=UserForm::where('id',$request->id)->first();
        $userForm->form_yeri = $userForm->sent_role;
        $userForm->sent_role = 4;
        $userForm->approval = 1;
        $userForm->save();
        return response()->json(['Success' => 'success']);
    }

    public function rejectForm(Request $request){
        $userForm=UserForm::where('id',$request->id)->first();
        $userForm->form_yeri = $userForm->sent_role;
        $userForm->sent_role = 4;
        $userForm->approval = 2;
        $userForm->save();
        return response()->json(['Success' => 'success']);
    }

    public function transaction_index()
    {
        return view('Enstitu.transactions');

    }

    public function transaction_fetch()
    {
        $transaction = Student::all();
        return DataTables::of($transaction)
            ->editColumn('institute_id', function ($data) {
                if (Institute::where('id', $data->institute_id)->first()) {
                    return Institute::where('id', $data->institute_id)->first()->name;
                } else {
                    return "Kayıtlı Enstitü Bulunamadı";
                }
            })
            ->make(true);
    }

    public function subs_fetch()
    {
        $subs = Academician::all();
        return DataTables::of($subs)
            ->editColumn('institute_id', function ($data) {
                if (Institute::where('id', $data->institute_id)->first()) {
                    return Institute::where('id', $data->institute_id)->first()->name;
                } else {
                    return "Kayıtlı Enstitü Bulunamadı";
                }
            })
            ->make(true);
    }

    public function checkEnstitu()
    {
        $user = Auth::user();
        if (isset($user->institute_id)) {
            $deger = 0;
        } else {
            $deger = 1;
        }
        return response()->json([
            'deger' => $deger
        ]);
    }

    public function changeEnstitu(Request $request)
    {
        $user = Auth::user();
        $user->institute_id = $request->institute_id;
        $user->save();
        return response()->json(['success' => 'success']);
    }

    //duyuru kısmı
    public function announcement_index()
    {
        $institutes = Institute::all();
        return view("Enstitu.announcement.index", compact('institutes'));
    }


    public function announcement_create(Request $request)
    {
        $request->validate(
            [
                "title" => "string|max:255|required",
                "contentt" => "required",

            ],
            [
                'title.required' => 'Duyuru Başlığı boş bırakılamaz.',
                'contentt.required' => 'Duyuru Açıklaması boş bırakılamaz.',

            ]
        );
        $announcement = new Announcement();
        $announcement->user_id = Auth::id();
        $announcement->title = Helper::scriptStripper($request->title);
        $announcement->content = Helper::scriptStripper($request->contentt);
        $announcement->institute_id = Auth::user()->institute_id;
        $announcement->save();
        return response()->json(['Success' => 'success']);
    }


    public function announcement_show(Request $request)
    {
        $announcements = Announcement::where('id', $request->id)->first();

        return response()->json(['title' => $announcements->title, 'content' => $announcements->content, 'institute_id' => $announcements->institute_id]);

    }


    public function announcement_fetch()
    {
        $announcements = Announcement::where('institute_id', Auth::user()->institute_id)->get();
        return DataTables::of($announcements)
            ->addColumn('update', function ($data) {
                return "<button onclick='updateAnnouncement(" . $data->id . ")' class='btn btn-warning'>Güncelle</button>";
            })->addColumn('delete', function ($data) {
                return "<button onclick='deleteAnnouncement(" . $data->id . ")' class='btn btn-danger'>Sil</button>";

            })->editColumn('institute_id', function ($data) {
                if (Institute::where('id', $data->institute_id)->first()) {
                    return Institute::where('id', $data->institute_id)->first()->name;
                } else {
                    return "Kayıtlı Enstitü Bulunamadı";
                }
            })->editColumn('user_id', function ($data) {
                if (User::where('id', $data->user_id)->first()) {
                    return User::where('id', $data->user_id)->first()->userFirstName;
                } else {
                    return "Kayıtlı Kullanıcı Bulunamadı";
                }
            })
            ->rawColumns(['update', 'delete', 'institutes', 'users'])
            ->make(true);
    }


    public function announcement_update(Request $request)
    {
        $request->validate([
            'title' => 'nullable',
            'contentt' => 'nullable',
        ]);

        $announcement = Announcement::find($request->updateId);
        $announcement->user_id = Auth::id();
        $announcement->title = Helper::scriptStripper($request->title);
        $announcement->content = Helper::scriptStripper($request->contentt);
        $announcement->save();

        return response()->json(['Success' => 'success']);
    }


    public function announcement_destroy(Request $request)
    {
        $request->validate([
            'id' => 'distinct'
        ]);
        Announcement::find($request->id)->delete();
        return response()->json(['Success' => 'success']);
    }


    /*    başvuru dönemi fonksiyonları*/

    public function finalizedAP(){

        return view('Enstitu.finalizedApplicationPeriods');
    }

    public function finalizedAPFetch(){
        $application_date = ApplicationDates::where('institutes_id', Auth::user()->institute_id)->where('is_active',0)->where('due_date','<',Carbon::now())->get();
        return DataTables::of($application_date)
            ->addColumn('results', function ($data) {
                return "<a  href='" . route('institute.asilList', ["application_date_id"=>$data->id, 'program'=>$data->program]) . "' class='btn btn-success'>Sonuçları Gör</a>";

            })->editColumn('period', function ($data) {
                if (ApplicationDates::where('period', $data->period)->first()) {
                    if ($data->period == 0) {
                        return "Güz";
                    } else {
                        return "Bahar";
                    }
                }
            })->editColumn('program', function ($data) {
                if (ApplicationDates::where('program', $data->program)->first()) {
                    if ($data->program == 3) {
                        return "Tezsiz Yüksek Lisans";
                    } elseif ($data->program == 0) {
                        return "Tezli Yüksek Lisans";
                    } elseif ($data->program == 1) {
                        return "Doktora";

                    }
                }
            })->editColumn('start_date', function ($data) {
                $start_date = date("d-m-Y", strtotime($data->start_date));
                return $start_date;

            })
            ->editColumn('due_date', function ($data) {
                $due_date = date("d-m-Y", strtotime($data->due_date));
                return $due_date;

            })
            ->rawColumns(['results', 'period', 'program','start_date','due_date'])
            ->make(true);

    }


}
