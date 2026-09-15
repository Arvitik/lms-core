<?php
namespace App\Http\Controllers;
use App\Group;
use App\Statements\Plans\CoursePlan;
use App\Statements\DAO\CoursePlanDAO;
use App\Statements\Passes\ControlWorkPasses;
use App\Statements\Passes\LecturePasses;
use App\Statements\Passes\SeminarPasses;
use App\Testing\Test;
use App\Testing\TestForGroup;
use App\TeacherHasGroup;
use App\News;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdministrationController extends Controller{
    const NEWS_FILE_DIR = 'download/news/';
    private $course_plan_DAO;


    public function __construct(CoursePlanDAO $course_plan_DAO){
        $this->course_plan_DAO = $course_plan_DAO;
    }

    public function checkEmailIfExists(Request $request){
        $email = $request->input('email');
        $users = User::where('email' , $email)->get();
        if (count($users) != 0) {
            return "exists";
        } else {
            return "notExists";
        }
    }

    public function verify()
    {
        $groups = Group::where('archived', 0)->get();
        $user = Auth::user();
        if ($user['role'] == 'Админ')
        {
            $query = User::whereRole("")->join('groups', 'groups.group_id', '=', 'users.group')->where('groups.archived', 0)->orderBy('id', 'desc')->get();
        } else {
            $query = TeacherHasGroup::join('users', 'teacher_has_group.group', '=', 'users.group')->join('groups', 'groups.group_id', '=', 'users.group')->where('teacher_has_group.user_id', $user['id'])->where('users.role', '')->select('users.first_name', 'users.last_name', 'users.role', 'users.group', 'users.email', 'users.id', 'groups.group_name')->distinct()->get();
        }
        return view('personal_account/verify_students', compact('query', 'groups'));
    }

    public function change_role(){
        $groups = Group::where('archived', 0)->orderBy('group_name')->get();
        $query = User::leftJoin('groups', 'groups.group_id', '=', 'users.group')
            ->select('users.*', 'groups.group_name');

        if (request('group') !== null && request('group') !== '') {
            $query->where('users.group', request('group'));
        }

        $role = request('role');
        if ($role === 'new' || (($role === null || $role === '') && (request('group') === null || request('group') === ''))) {
            $query->where(function ($roles) {
                $roles->where('users.role', '')
                    ->orWhereNull('users.role');
            });
        } elseif ($role !== null && $role !== '' && $role !== 'all') {
            $query->where('users.role', $role);
        }

        if (request('email') !== null && request('email') !== '') {
            $query->where('users.email', 'like', '%' . request('email') . '%');
        }

        if (request('last_name') !== null && request('last_name') !== '') {
            $query->where('users.last_name', 'like', '%' . request('last_name') . '%');
        }

        $query = $query->orderBy('users.id', 'desc')
            ->get();

        return view('personal_account/change_role', compact('query', 'groups'));
    }

    public function add_groups(){
        $groups = Group::orderBy('group_id', 'desc')->get();
        $course_plans = CoursePlan::all('id_course_plan', 'course_plan_name');
        return view('personal_account/add_groups', compact('groups', 'course_plans'));
    }

    public function add_group_to_set(Request $request){
        $name = $request->input('name');
        $description = $request->input('description');
        $id_course_plan = $request->input('id_course_plan');
        if (empty($id_course_plan)) {
            $id_course_plan = null;
        }
        $validate = Group::whereGroup_name($name)->get();
        if(count($validate) == 0){
            Group::insert(['group_name' => $name, 'description' => $description, 'archived' => 0, 'id_course_plan' => $id_course_plan]);
            $group_id = Group::whereGroup_name($name)->select('group_id')->first()->group_id;

            // add group availability for active tests
            $active_tests = Test::whereArchived(0)->select('id_test')->get();
            foreach ($active_tests as $test) {
                TestForGroup::insert(['id_test' => $test['id_test'], 'id_group' => $group_id, 'availability' => 0]);
            }
        }
        return redirect()->route('group_set');
    }

    public function delete_group_from_set(Request $request){
        $id = json_decode($request->input('number'),true);
        Group::where('group_id', $id)->update(['archived' => 1]);
        return $id;
    }


    public function archived_group_restore(Request $request){
        $id = json_decode($request->input('number'),true);
        Group::where('group_id', $id)->update(['archived' => 0]);
        return $id;
    }


    public function change_group(Request $request){
        $id = json_decode($request->input('id'),true);
        $value = $request->input('value');
        User::where('id', $id)->update(['group' => $value]);
        return 0;
    }

    public function remove_user(Request $request){
        $id = json_decode($request->input('id'),true);
        User::find($id)->delete();
        return 0;
    }

    public function change_l_name(Request $request){
        $id = json_decode($request->input('id'),true);
        $value = $request->input('value');
        User::where('id', $id)->update(['last_name' => $value]);
        return 0;
    }
    public function change_f_name(Request $request){
        $id = json_decode($request->input('id'),true);
        $value = $request->input('value');
        User::where('id', $id)->update(['first_name' => $value]);
        return 0;
    }

    public function manage_groups(){
        $teachers = User::
            join('groups', 'groups.group_id', '=', 'users.group')
            ->where('users.role', 'Преподаватель')
            ->where('groups.archived', 0)
            ->orderBy('id', 'desc')->get();
        $group_set = Group::where('archived', 0)->get();
        $groups = TeacherHasGroup::join('users', 'teacher_has_group.user_id', '=', 'users.id')
            ->join('groups', 'groups.group_id', '=', 'teacher_has_group.group')
            ->where('groups.archived', 0)
            ->select('teacher_has_group.user_id', 'teacher_has_group.group', 'teacher_has_group.id', 'users.first_name', 'users.last_name', 'groups.group_name')
            ->get();
        return view('personal_account/manage_groups', compact('teachers', 'groups', 'group_set'));
    }

    public function manage_news(){
        $news = News::get();
        return view('personal_account/manage_news', compact('news'));
    }

    public function delete_group(Request $request){
        $id = json_decode($request->input('id'),true);
        TeacherHasGroup::where('id', $id)->delete();
        return $id;
    }

    public function delete_news(Request $request){
        $id = json_decode($request->input('id'),true);
        News::where('id', $id)->delete();
        return $id;
    }

    public function hide_news(Request $request){
        $id = json_decode($request->input('id'),true);
        $news = News::where('id', $id)->first();
        if ( $news['is_visible'] == 0){
            News::where('id', $id)->update(['is_visible' => 1]);
        } else {
            News::where('id', $id)->update(['is_visible' => 0]);
        }
        return $id;
    }

    public function add_group(Request $request){
        $teacher = $request->input('teacher');
        $group = $request->input('group');
        TeacherHasGroup::insert(['user_id' => $teacher, 'group' => $group]);
        return redirect()->route('manage_groups');
    }

    public function add_news(Request $request){
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'file' => 'nullable|file|max:10240',
        ], [
            'file.max' => 'Размер файла не должен превышать 10 МБ.',
        ]);

        $news = new News();
        $news->body = $request->input('body');
        $news->title = $request->input('title');
        $news->is_visible = 1;
        if ($request->hasFile('file')) {
            if ($request->file('file')->isValid()) {
                $file = $request->file('file');
                $filename = mt_rand(0, 10000). '_' . $file->getClientOriginalName();
                $file->move($this::NEWS_FILE_DIR, $filename);
                $news->file_path = $this::NEWS_FILE_DIR . $filename;
            } else {
                return back()->withInput()->withErrors(['Ошибка при загрузке файла']);
            }
        }
        $news->save();
        return redirect()->route('manage_news');
    }
    public function manageUsers(Request $request)
    {
        $query = User::leftJoin('groups', 'groups.group_id', '=', 'users.group')
            ->select('users.*', 'groups.group_name');

        if (trim((string) $request->input('email')) !== '') {
            $query->where('users.email', 'like', '%' . $request->email . '%');
        }

        if (trim((string) $request->input('last_name')) !== '') {
            $query->where('users.last_name', 'like', '%' . $request->last_name . '%');
        }

        if ((string) $request->input('group') !== '') {
            $query->where('users.group', $request->group);
        } else {
            // По умолчанию показываем ожидающих назначения и сотрудников.
            $query->where(function ($roles) {
                $roles->whereNotIn('users.role', ['Студент', 'Староста'])
                    ->orWhereNull('users.role');
            });
        }

        $users = $query->orderBy('users.id', 'desc')->get();

        $groups = DB::table('groups')
            ->where('archived', 0)
            ->select('group_id', 'group_name')
            ->orderBy('group_name')
            ->get();

        return view('admin.manage_users', compact('users', 'groups'));
    }



    public function bulkAction(Request $request)
    {   
        $ids = $request->input('selected_users', []);
        $action = $request->input('action');

        foreach ($ids as $id) {
            $user = User::find($id);
            if (!$user) continue;

            switch ($action) {
                case 'delete':
                    $user->delete();
                    break;

                case 'make_monitor':
                    // сначала создаём ведомости через add_student
                    $this->add_student(new Request(['id' => json_encode($user->id)]));

                    // затем обновляем роль на "Староста"
                    $user->role = 'Староста';
                    $user->save();
                    break;

                case 'set_teacher':
                    $this->setStaffRole($user, 'Преподаватель');
                    break;

                case 'set_average':
                    $user->role = 'Обычный';
                    $user->save();
                    break;

                case 'set_student':
                    // используем add_student, чтобы и роль выставилась, и ведомости создались
                    $this->add_student(new Request(['id' => json_encode($user->id)]));
                    break;

                case 'set_senior_teacher':
                    $this->setStaffRole($user, 'Старший преподаватель');
                    break;

                case 'set_admin':
                    $this->setStaffRole($user, 'Админ');
                    break;
            }
        }

        return redirect()->back();
    }



//Данные методы меняют роль выбранного юзера
    public function add_student(Request $request)
    {
        $id = json_decode($request->input('id'), true);

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'errors' => ['Пользователь не найден'],
                'id' => $id
            ]);
        }

        $group = Group::whereGroup_id($user->group)->first();
        if (!$group) {
            return response()->json([
                'errors' => ['Группа не найдена для пользователя ID '.$user->id],
                'id' => $id
            ]);
        }

        $id_course_plan = $group->id_course_plan;
        $validator = $this->getValidateAddStudent($id_course_plan, $group);

        if ($validator->passes()) {
            // если это не студент и не староста → делаем студентом
            if ($user->role !== 'Студент') {
                $user->role = 'Студент';
                $user->save();
            }

            // если нет ведомостей → создаём их (и для студента, и для старосты)
            if (!LecturePasses::where('id_user', $user->id)->exists()) {
                foreach ($this->course_plan_DAO->getAllLectures($id_course_plan) as $lecture) {
                    LecturePasses::insert([
                        'id_lecture_plan' => $lecture->id_lecture_plan,
                        'id_user' => $user->id,
                        'presence' => 0
                    ]);
                }

                foreach ($this->course_plan_DAO->getAllSeminars($id_course_plan) as $seminar) {
                    SeminarPasses::insert([
                        'id_seminar_plan' => $seminar->id_seminar_plan,
                        'id_user' => $user->id,
                        'presence' => 0,
                        'work_points' => 0
                    ]);
                }

                foreach (
                    $this->course_plan_DAO->getAllControlWorks($id_course_plan)
                        ->merge($this->course_plan_DAO->getAllExamWorks($id_course_plan))
                    as $control_work
                ) {
                    ControlWorkPasses::insert([
                        'id_control_work_plan' => $control_work->id_control_work_plan,
                        'id_user' => $user->id,
                        'presence' => 0,
                        'points' => 0
                    ]);
                }
            }

            return response()->json(['id' => $id]);
        } else {
            return response()->json([
                'errors' => $validator->errors()->all(),
                'id' => $id
            ]);
        }
    }



    public function getValidateAddStudent($id_course_plan, $group) {
        $validator = Validator::make([
            'id_course_plan' => $id_course_plan,
            'group_name' => $group->group_name
        ], []);
        $validator->after(function ($validator) {
            $id_course_plan = $validator->getData()['id_course_plan'];
            $group_name = $validator->getData()['group_name'];
            if($id_course_plan == null) {
                $validator->errors()->add('without_course_plan', 'Назначьте учебный план для группы: ' . $group_name);
            } else {
                if (!CoursePlanDAO::checkPointsCoursePlan($id_course_plan)->passes()) {
                $course_plan_name = CoursePlan::where('id_course_plan', $id_course_plan)
                    ->first()->course_plan_name;
                $validator->errors()->add('not_valid_points', 'Баллы учебного плана('.$course_plan_name.') не корректны');
                }
            }
        });
        return $validator;
    }

    public function add_admin(Request $request){
        $id = json_decode($request->input('id'),true);
        $user = User::find($id);
        $this->setStaffRole($user, 'Админ');
        return $id;
    }
    public function add_average(Request $request){
        $id = json_decode($request->input('id'),true);
        $user = User::find($id);
        if($user['role'] != 'Обычный') {
            $user->role = 'Обычный';
            $user->save();
        }
        return $id;
    }

    public function add_tutor(Request $request){
        $id = json_decode($request->input('id'),true);
        $user = User::find($id);
        $this->setStaffRole($user, 'Преподаватель');
        return $id;
    }

    private function setStaffRole(User $user, $role)
    {
        $adminGroupId = Group::where('group_name', 'Админы')->value('group_id');
        $user->role = $role;
        if ($adminGroupId) {
            $user->group = $adminGroupId;
        }
        $user->save();
    }

    public static function getAdminPanel(){
        return view('personal_account/adminPanel');
    }

    public function pashalka() {
        return redirect()->route('home');
    }
}
