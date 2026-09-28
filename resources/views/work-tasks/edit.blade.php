@include('work-tasks._form', [
    'task'        => $task,
    'formAction'  => route('work-tasks.update', $task),
    'method'      => 'PATCH',
    'submitLabel' => 'Save Changes',
    'pageTitle'   => 'Edit Task',
])
