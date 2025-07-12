<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use Illuminate\Http\Request;

class ListItemController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        ray('hier');
        $offset = $request->get('from');
        $limit = $request->get('till');
        $listItems = ListItem::select('*');

        if(isset($offset) && isset($limit)){
            $listItems->limit($limit)
                    ->offset($offset);
        }


        return response()->json([
            'data' => $listItems->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        ray($request->all());
        $listItem = ListItem::create($request->all());

        return response()->json([
            'data' => $listItem,
        ]);
    }
}
