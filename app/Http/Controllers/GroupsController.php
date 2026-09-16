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

class GroupsController extends Controller{
    public function manageGroups() {
        $groupsSrc = $this->loadGroups();
        $groups = [];
        foreach ($groupsSrc as $group) {
            $groups[] = array_merge($group, [
                'teachers' => $this->loadTeachersByGroup($group['id']),
            ]);
        }
        return view('personal_account/manage_groups_elite', compact('groups'));
    }

    public function manageGroupsByTeachers() {
        $teachersSrc = $this->loadTeachers();
        $teachers = [];
        foreach ($teachersSrc as $teacher) {
            $teachers[] = array_merge($teacher, [
                'groups' => $this->loadGroupsByTeacher($teacher['id']),
            ]);
        }
        return view('personal_account/manage_groups_by_teachers_elite', compact('teachers'));
    }

    public function otherTeachers(Request $request) {
        $data = $this->getData($request);
        $groupId = $data->groupId;

        $group = $this->loadGroup($groupId);
        $allTeachers = $this->loadTeachers();
        $groupTeachers = $this->loadTeachersByGroup($groupId);
        $otherTeachers = $this->subtract($allTeachers, $groupTeachers);

        $res = [
            'group' => $group,
            'otherTeachers' => $otherTeachers,
        ];
        return response()->json($res);
    }
    
    public function otherGroups(Request $request) {
        $data = $this->getData($request);
        $teacherId = $data->teacherId;

        $teacher = $this->loadTeacher($teacherId);
        $allGroups = $this->loadGroups();
        $teacherGroups = $this->loadGroupsByTeacher($teacherId);
        $otherGroups = $this->subtract($allGroups, $teacherGroups);

        $res = [
            'teacher' => $teacher,
            'otherGroups' => $otherGroups,
        ];
        return response()->json($res);
    }

    public function addTeachersToGroup(Request $request) {
        $data = $this->getData($request);
        $groupId = $data->groupId;
        $teacherIds = $data->teacherIds;
        foreach ($teacherIds as $id) {
            TeacherHasGroup::firstOrCreate(['user_id' => $id, 'group' => $groupId]);
        }
        $teachers = $this->loadTeachersByGroup($groupId);
        return response()->json($teachers);
    }

    public function addGroupsToTeacher(Request $request) {
        $data = $this->getData($request);
        $teacherId = $data->teacherId;
        $groupIds = $data->groupIds;
        foreach ($groupIds as $id) {
            TeacherHasGroup::firstOrCreate(['user_id' => $teacherId, 'group' => $id]);
        }
        $groups = $this->loadGroupsByTeacher($teacherId);
        return response()->json($groups);
    }

    public function removeTeacherFromGroup(Request $request) {
        $data = $this->getData($request);
        $groupId = $data->groupId;
        $teacherId = $data->teacherId;
        $this->removeGroupTeacherLink($groupId, $teacherId);
        $teachers = $this->loadTeachersByGroup($groupId);
        return response()->json($teachers);
    }
    
    public function removeGroupFromTeacher(Request $request) {
        $data = $this->getData($request);
        $groupId = $data->groupId;
        $teacherId = $data->teacherId;
        $this->removeGroupTeacherLink($groupId, $teacherId);
        $groups = $this->loadGroupsByTeacher($teacherId);
        return response()->json($groups);
    }

    private function getData(Request $request) {
        return json_decode($request->input('data'), false);
    }

    private function convertTeacher($teacher) {
        return [
            'id' => $teacher->id,
            'lastName' => $teacher->last_name,
            'firstName' => $teacher->first_name,
        ];
    }

    private function convertGroup($group) {
        return [
            'id' => $group->group_id,
            'name' => $group->group_name,
        ];
    }

    /**
     * returns [
     *  {
     *      id,
     *      lastName,
     *      firstName,
     *  },
     *  ...
     * ]
     */
    private function loadTeachers($teacherIds = null) {
        $activeTeacherIds = TeacherHasGroup::join('groups', 'groups.group_id', '=', 'teacher_has_group.group')
            ->where('groups.archived', 0)
            ->where('groups.group_name', '!=', 'Админы')
            ->pluck('teacher_has_group.user_id')
            ->unique()
            ->values()
            ->all();
        $assignedTeacherIds = TeacherHasGroup::pluck('user_id')->unique()->values()->all();

        $teachers = User::join('groups as account_groups', 'account_groups.group_id', '=', 'users.group')
            ->where('account_groups.group_name', 'Админы')
            ->whereNull('users.deleted_at')
            ->whereIn('users.role', [
                'Преподаватель',
                'Старший преподаватель',
                'Админ',
            ])
            ->where(function ($staff) use ($activeTeacherIds, $assignedTeacherIds) {
                $staff->where('users.role', 'Админ')
                    ->orWhere(function ($teachers) use ($activeTeacherIds, $assignedTeacherIds) {
                        $teachers->whereIn('users.role', ['Преподаватель', 'Старший преподаватель'])
                            ->where(function ($activity) use ($activeTeacherIds, $assignedTeacherIds) {
                                $activity->whereIn('users.id', $activeTeacherIds);
                                if (!empty($assignedTeacherIds)) {
                                    $activity->orWhereNotIn('users.id', $assignedTeacherIds);
                                }
                            });
                    });
            })
            ->select('users.*');
        if ($teacherIds !== null) {
            $teachers = $teachers->whereIn('id', $teacherIds);
        }
        return $teachers
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function($x) {
                return $this->convertTeacher($x);
            })
            ->values();
    }

    /**
     * returns null or
     *  {
     *      id,
     *      lastName,
     *      firstName,
     *  }
     */
    private function loadTeacher($teacherId) {
        $teachers = $this->loadTeachers([$teacherId]);
        if (count($teachers) <= 0) {
            return null;
        }
        return $teachers[0];
    }

    /**
     * returns [
     *  {
     *      id,
     *      name,
     *  },
     *  ...
     * ]
     */
    private function loadGroups($groupIds = null) {
        $groups = Group::
            where('archived', 0)
            ->where('group_name', '!=', 'Админы');
        if ($groupIds !== null) {
            $groups = $groups->whereIn('group_id', $groupIds);
        }
        return $groups
            ->orderBy('group_id', 'desc')
            ->get()->map(function ($x) {
                return $this->convertGroup($x);
            })->values();
    }

    /**
     * returns null or
     *  {
     *      id,
     *      name,
     *  }
     */
    private function loadGroup($groupId) {
        $groups = $this->loadGroups([$groupId]);
        if (count($groups) <= 0) {
            return null;
        }
        return $groups[0];
    }

    private function loadTeachersByGroup($groupId) {
        $teacherIds = TeacherHasGroup::
            where('group', $groupId)
            ->get()
            ->map(function ($x) {
                return $x->user_id;
            })
            ->values();
        return $this->loadTeachers($teacherIds);
    }

    private function loadGroupsByTeacher($teacherId) {
        $groupIds = TeacherHasGroup::
            where('user_id', $teacherId)
            ->get()
            ->map(function ($x) {
                return $x->group;
            })
            ->values();
        return $this->loadGroups($groupIds);
    }

    private function removeGroupTeacherLink($groupId, $teacherId) {
        TeacherHasGroup::
            whereUserId($teacherId)
            ->whereGroup($groupId)
            ->delete();
    }

    private function subtract($aCollection, $bCollection) {
        return $aCollection->filter(function($a) use ($bCollection) {
            return !$bCollection->contains('id', $a['id']);
        })->values();
    }
}

