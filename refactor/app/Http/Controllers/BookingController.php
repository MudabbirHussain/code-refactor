<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

class BookingController extends Controller
{
    protected $repository;

    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    public function index(Request $request)
    {
        $user_id = $request->get('user_id');

        if ($user_id) {
            return response($this->repository->getUsersJobs($user_id));
        }

        if (in_array($request->__authenticatedUser->user_type, [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')])) {
            return response($this->repository->getAll($request));
        }

        return null;
    }

    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response($job);
    }

    public function store(Request $request)
    {
        $response = $this->repository->store($request->__authenticatedUser, $request->all());
        return response($response);
    }

    public function update($id, Request $request)
    {
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, array_except($request->all(), ['_token', 'submit']), $cuser);
        return response($response);
    }

    public function immediateJobEmail(Request $request)
    {
        $response = $this->repository->storeJobEmail($request->all());
        return response($response);
    }

    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');

        if ($user_id) {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    public function acceptJob(Request $request)
    {
        $response = $this->repository->acceptJob($request->all(), $request->__authenticatedUser);
        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $response = $this->repository->acceptJobWithId($request->get('job_id'), $request->__authenticatedUser);
        return response($response);
    }

    public function cancelJob(Request $request)
    {
        $response = $this->repository->cancelJobAjax($request->all(), $request->__authenticatedUser);
        return response($response);
    }

    public function endJob(Request $request)
    {
        $response = $this->repository->endJob($request->all());
        return response($response);
    }

    public function customerNotCall(Request $request)
    {
        $response = $this->repository->customerNotCall($request->all());
        return response($response);
    }

    public function getPotentialJobs(Request $request)
    {
        $response = $this->repository->getPotentialJobs($request->__authenticatedUser);
        return response($response);
    }


    public function distanceFeed(Request $request)
    {
        $data = $request->all();
        $jobId = $data['jobid'] ?? null;
        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $session = $data['session_time'] ?? '';
        $adminComment = $data['admincomment'] ?? '';
        $flagged = $data['flagged'] === 'true' ? 'yes' : 'no';
        $manuallyHandled = $data['manually_handled'] === 'true' ? 'yes' : 'no';
        $byAdmin = $data['by_admin'] === 'true' ? 'yes' : 'no';

        if ($flagged === 'yes' && $adminComment === '') {
            return response('Please, add comment');
        }

        if (!empty($time) || !empty($distance)) {
            $affectedRows = Distance::where('job_id', '=', $jobId)->update([
                'distance' => $distance,
                'time' => $time,
            ]);
        }

        if (!empty($session) || !empty($adminComment) || $flagged !== 'no' || $manuallyHandled !== 'no' || $byAdmin !== 'no') {
            $affectedRows1 = Job::where('id', '=', $jobId)->update([
                'admin_comments' => $adminComment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manuallyHandled,
                'by_admin' => $byAdmin,
            ]);
        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
