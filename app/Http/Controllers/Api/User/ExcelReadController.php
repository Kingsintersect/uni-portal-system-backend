<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\ApplicationPayment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Illuminate\Support\Str;

class ExcelReadController extends Controller
{
    public static function import($request)
    {
        // Validate the file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);


        $columns = [
            "full_name",
            "gender",
            "password",
            "email",
            "phone_number",
            // "reg_number",
            // "dob",
            // "level",
            "DEPARTMENT_ID",
            "nationality",
            "state",
        ];

        $namesArray = ['last_name', 'first_name', 'other_name'];
        $escapeField = ['full_name'];

        $requiredColumns = [
            "full_name",
            'last_name',
            'first_name',
            // "gender",
            // "password",
            // "email",
            // "phone_number",
            // "reg_number",
            // "dob",
            // "level",
            "DEPARTMENT_ID"
        ];

        $uniqueColumns = ["email"]; // "reference",

        // Get the uploaded file
        $file = $request->file('file');

        $nonEmailUsers = [];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = self::posted_values($sheet->toArray());

            $pack = [];

            foreach ($data as $k => $d) {
                if ($k > 0) {
                    $p1 = [];

                    if (empty($d[0]) && empty($d[1])) {
                        continue;
                    }
                    foreach ($d as $v1 => $d1) {

                        if (in_array($columns[$v1], $requiredColumns) && empty($d1)) {
                            // var_dump($columns[$v1]);

                            // return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is REQUIRED in Line " . ($k + 1), ""];
                        }


                        if ($columns[$v1] == 'full_name') {
                            $explode = explode(' ', trim(str_replace('  ', ' ', $d1)));
                            $_cnt = 0;
                            foreach ($explode as $b => $name) {
                                if (!empty($name)) {
                                    if (count($namesArray) > $_cnt) {
                                        $p1[$namesArray[$_cnt]] = $name ?? null;
                                        $_cnt++;
                                    }
                                }
                            }
                        }


                        if ($columns[$v1] == 'DEPARTMENT_ID') {
                            // search department
                            $cDep = Department::where(['department_name' => trim($d1)])->get();
                            if (!$cDep->isEmpty()) {
                                $cDep = $cDep[0];
                                $d1 = $cDep->id; // department_id
                                $p1['faculty_id'] = $cDep->faculty_id;
                            } else {
                                // Empty department
                                return [ucwords($d[0] . " " . $d[1]) . ", Unknown department " . $d1 . " in Line " . ($k + 1), []];
                            }
                        }

                        // unique columns
                        if (in_array($columns[$v1], $uniqueColumns)) {

                            if ($columns[$v1] == 'email' && empty($d1)) {
                                $d1 = null;
                                $p1['email'] = empty($d1) ? null : $d1;
                                $nonEmailUsers[] = ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is empty";
                            } else {
                                $cDep = User::where([$columns[$v1] => $d1])->get();
                                if (!$cDep->isEmpty()) {
                                    return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is already TAKEN in Line " . ($k + 1), []];
                                }
                            }
                        }
                        if ($columns[$v1] == 'password') {
                            $d1 = bcrypt($d1);
                        }

                        // Generate reference no
                        $ref_number = Carbon::now()->format('YmdHisv') . Str::random(6);

                        $cDep = User::where(['reference' => $ref_number])->get();
                        if (!$cDep->isEmpty()) {
                            for ($i = 1; $i < 120; $i++) {
                                $ref_number = Carbon::now()->format('YmdHisv') . Str::random(6);
                                $cDep = User::where(['reference' => $ref_number . $i])->get();
                                if ($cDep->isEmpty()) {
                                    $p1['reference'] = $ref_number;
                                }
                            }
                        } else {
                            $p1['reference'] = $ref_number;
                        }

                        $p1['is_applied'] = 0;
                        $p1['application_payment_status'] = 1;
                        $p1['amount'] = 0;
                        $p1['created_at'] = Carbon::now();

                        if (!in_array($columns[$v1], $escapeField)) $p1[$columns[$v1]] = empty($d1) ? null : $d1;
                    }

                    $pack[] = $p1;
                }
            }

            if (!empty($pack)) {

                DB::table('application_payments')->insert($pack);
                return ["success", $nonEmailUsers];
            }

            return ["error", []];
        } catch (Exception $e) {
            return ["Error 501", []];
        }
    }

    // latestCollection
    public static function latestMultiUserPatchName($request)
    {
        // Validate the file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);


        $columns = [
            "full_name",
            "first_name",
            "last_name",
            "email",
            "gender",
            "username",
            "phone_number",
            "dob",
            "level",
            "role",
            "program",
            "DEPARTMENT_ID",
            "nationality",
            "state",
            "address",
            "lga",
            "hometown_address",
            "residential_address",
            "religion",
            "disability",
            "other_disability"
        ];

        $namesArray = ['last_name', 'first_name', 'other_name'];
        $escapeField = ['full_name'];

        $requiredColumns = [
            "full_name",
            'last_name',
            'first_name',
            // "gender",
            // "password",
            // "email",
            // "phone_number",
            // "reg_number",
            // "dob",
            // "level",
            "DEPARTMENT_ID"
        ];

        $uniqueColumns = ["email"]; // "reference",

        // Get the uploaded file
        $file = $request->file('file');

        $nonEmailUsers = [];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = self::posted_values($sheet->toArray());

            $pack = [];

            foreach ($data as $k => $d) {
                if ($k > 0) {
                    $p1 = [];

                    if (empty($d[0]) && empty($d[1])) {
                        continue;
                    }
                    foreach ($d as $v1 => $d1) {

                        $isFullName = false;
                        if ($columns[$v1] == 'full_name') {
                            if (!empty($columns[$v1])) {
                                $isFullName = true;
                                $explode = explode(' ', trim(str_replace('  ', ' ', $d1)));
                                $_cnt = 0;
                                foreach ($explode as $b => $name) {
                                    if (!empty($name)) {

                                        if (count($namesArray) > $_cnt) {
                                            $p1[$namesArray[$_cnt]] = $name ?? null;
                                            $currentNameName = $namesArray[$_cnt];
                                            $pp = array_filter($columns, function ($item) use ($currentNameName) {
                                                return  $item == $currentNameName;
                                            });
                                            $nameColumnIndexKey = array_keys($pp)[0];

                                            // dd($data[$k]);

                                            $d[$nameColumnIndexKey] = $name ?? $d[$nameColumnIndexKey];
                                            $data[$k][$nameColumnIndexKey] = $name ?? $d[$nameColumnIndexKey];
                                            $_cnt++;
                                        }
                                    }
                                }
                            }
                        }

                        // Check for last_name and other names array that has been filled via FullName
                        if (in_array($columns[$v1], $namesArray)) {
                            // Important to add this up because changes doesn't affect original
                            $d1 = $d[$v1];
                        }


                        if ($columns[$v1] == 'DEPARTMENT_ID') {
                            // search department
                            $cDep = Department::where(['department_name' => trim($d1)])->get();
                            if (!$cDep->isEmpty()) {
                                $cDep = $cDep[0];
                                $d1 = $cDep->id; // department_id
                                $p1['faculty_id'] = $cDep->faculty_id;
                            } else {
                                // Empty department
                                return [ucwords($d[0] . " " . $d[1]) . ", Unknown department " . $d1 . " in Line " . ($k + 1), []];
                            }
                        }

                        // unique columns
                        if (in_array($columns[$v1], $uniqueColumns)) {
                            if ($columns[$v1] == 'email' && empty($d1)) {
                                $d1 = null;
                                $p1['email'] = empty($d1) ? null : $d1;
                                $nonEmailUsers[] = ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is empty";
                            } else {
                                $cDep = ApplicationPayment::where([$columns[$v1] => $d1])->get();
                                if (!$cDep->isEmpty()) {
                                    return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is already TAKEN in Line " . ($k + 1), []];
                                }
                            }
                        }
                        if ($columns[$v1] == 'password') {
                            $d1 = bcrypt($d1);
                        }

                        if (!in_array($columns[$v1], $escapeField)) $p1[$columns[$v1]] = empty($d1) ? null : $d1;
                    }

                    $ref_number = Carbon::now()->format('YmdHisv') . Str::random(6);

                    $cDep = ApplicationPayment::where(['reference' => $ref_number])->get();

                    if (!$cDep->isEmpty()) {
                        for ($i = 1; $i < 120; $i++) {
                            $ref_number = Carbon::now()->format('YmdHisv') . Str::random(6);
                            $cDep = ApplicationPayment::where(['reference' => $ref_number . $i])->get();
                            if ($cDep->isEmpty()) {
                                $p1['reference'] = $ref_number;
                            }
                        }
                    } else {
                        $p1['reference'] = $ref_number;
                    }

                    $p1['password'] =  bcrypt('password');;
                    $p1['is_applied'] = 0;
                    $p1['application_payment_status'] = 1;
                    $p1['amount'] = 0;
                    $p1['created_at'] = Carbon::now();

                    $pack[] = $p1;
                }
            }
            // dd($pack);



            if (!empty($pack)) {
                DB::table('application_payments')->insert($pack);
                return ["success", $nonEmailUsers];
            }

            return ["error", []];
        } catch (Exception $e) {
            return ["Error 501", []];
        }
    }


    public static function patchName($request)
    {
        // Updating other name

        // Validate the file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);


        $columns = [
            "full_name",
            "gender",
            "password",
            // "reference",
            "email",
            "phone_number",
            // "reg_number",
            // "dob",
            // "level",
            "DEPARTMENT_ID",
            "nationality",
            "state",
        ];

        $namesArray = ['last_name', 'first_name', 'other_name'];
        $escapeField = ['full_name', 'gender', 'password', 'phone_number', 'DEPARTMENT_ID', 'nationality', 'state'];

        $requiredColumns = [
            "full_name",
            // "gender",
            // "password",
            // "reference",
            // "email",
            // "phone_number",
            // "reg_number",
            // "dob",
            // "level",
            // "DEPARTMENT_ID"
        ];

        $uniqueColumns = ["email"]; // "reference",

        // Get the uploaded file
        $file = $request->file('file');

        $nonEmailUsers = [];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = self::posted_values($sheet->toArray());

            $pack = [];

            foreach ($data as $k => $d) {
                // Change this if k0 is not a heading
                if ($k > 0) {
                    $p1 = [];

                    if (empty($d[0]) && empty($d[1])) {
                        continue;
                    }

                    foreach ($d as $v1 => $d1) {


                        if (in_array($columns[$v1], $requiredColumns) && empty($d1)) {

                            // return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " is REQUIRED in Line " . ($k + 1), ""];
                        }


                        // unique columns
                        if (in_array($columns[$v1], $uniqueColumns)) {
                            $cDep = User::where([$columns[$v1] => $d1])->get();

                            if ($cDep->isEmpty()) {
                                return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " not found in Line " . ($k + 1), []];
                            }
                            $cDep = $cDep[0];
                            if ($cDep->{$columns[$v1]} != $d1) {
                                return [ucwords($d[0] . " " . $d[1]) . ", " . strtoupper($columns[$v1]) . " Do not match " . $d1 . " in Line " . ($k + 1), []];
                            }
                            $p1[$columns[$v1]] = $d1;
                        }


                        if ($columns[$v1] == 'full_name') {
                            $explode = explode(' ', trim(str_replace('  ', ' ', $d1)));
                            $_cnt = 0;
                            foreach ($explode as $b => $name) {
                                if (!empty($name)) {
                                    if (count($namesArray) > $_cnt) {
                                        $p1[$namesArray[$_cnt]] = $name ?? null;
                                        $_cnt++;
                                    }
                                }
                            }
                        }


                        if (!in_array($columns[$v1], $escapeField)) $p1[$columns[$v1]] = empty($d1) ? null : $d1;
                    }

                    $pack[] = $p1;
                }
            }

            if (!empty($pack)) {
                // Update
                $up = self::updateMany('application_payments', $pack, 'email');
                // DB::table('application_payments')->insert($pack);
                return ["success", $nonEmailUsers];
            }

            return ["error", []];
        } catch (Exception $e) {
            return ["Error 501", []];
        }
    }

    public static function posted_values($post)
    {
        $clean_ary = [];
        foreach ($post as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $clean_ary[$key][$k] = self::sanitize(trim($v));
                }
            } else {
                $clean_ary[$key] = self::sanitize(trim($value));
            }
        }
        return $clean_ary;
    }

    public static function sanitize($dirty)
    {
        if (is_array($dirty)) {
            if (!empty($dirty)) {
                $h = [];
                foreach ($dirty as $k => $v) {
                    $h[$k] = htmlentities($v, ENT_QUOTES, 'UTF-8');
                }
                return $h;
            }
        }
        return htmlentities($dirty, ENT_QUOTES, 'UTF-8');
    }




    public static function updateMany($table, array $data, $uniqueColumn = 'email')
    {
        $firstnames = [];
        $lastnames = [];
        $othernames = [];
        $emails = [];

        foreach ($data as $row) {
            $row['other_name'] = $row['other_name'] ?? null;
            $email = $row[$uniqueColumn]; // Unique email identifier
            // $firstnames[] = "WHEN '{$email}' THEN '{$row['firstname']}'";
            // $lastnames[] = "WHEN '{$email}' THEN '{$row['lastname']}'";
            $othernames[] = "WHEN '{$email}' THEN '{$row['other_name']}'";
            $emails[] = "'{$email}'";
        }
        // dd($othernames);

        // $firstnames = implode(' ', $firstnames);
        // $lastnames = implode(' ', $lastnames);
        $othernames = implode(' ', $othernames);
        $emails = implode(',', $emails);

        // -- firstname = CASE {$uniqueColumn} {$firstnames} END, 
        // --   lastname = CASE {$uniqueColumn} {$lastnames} END, 
        $query = "UPDATE {$table} 
              SET 
                  other_name = CASE {$uniqueColumn} {$othernames} END
              WHERE {$uniqueColumn} IN ({$emails})";

        return DB::update($query);
    }
}




// Alter table users
// add `hometown_address` varchar(255) DEFAULT NULL,
//  add `lga` varchar(255) DEFAULT NULL,
//  add `residential_address` varchar(255) DEFAULT NULL,
//  add `religion` varchar(255) DEFAULT NULL,
//  add `disability` varchar(255) DEFAULT NULL,
//  add `other_disability` varchar(255) DEFAULT NULL
//  add `dob` date DEFAULT NULL
// add `program` varchar(255) DEFAULT NULL
// add `role` varchar(255) DEFAULT NULL
// add `username` varchar(255) DEFAULT NULL
