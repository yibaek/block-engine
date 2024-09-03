<?php
namespace middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Exception;
use Throwable;

use models\s3\S3Mgr;
use libraries\util\PlanUtil;
use libraries\log\LogMessage;

class SetPlanData
{
    private $ci;

    /**
     * SetPlanData constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     * @throws Throwable
     */
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        $storage = $this->ci->get('storage');

        // get header, bizunit info
        $headers = ($request->getAttribute('headers'))->getHeaders();
        $bizunitInfo = $this->getBizunitInfo($request, $headers);

        // get plan data
        $planData = $this->getPlanData($storage, $headers, $bizunitInfo);
        if (null === $planData) {
            $response = $response->withStatus(404);
            $response->getBody()->write($this->makeNotfoundMessage($bizunitInfo));
            return $response;
        }

        // set plan data and bizunit info
        $request = $request->withAttribute('planData', $planData);
        $request = $request->withAttribute('bizunitInfo', $bizunitInfo);

        // define plan version
        define('PLAN_VERSION', $this->getPlanVersion($planData));

        return $next($request, $response);
    }

    /**
     * @param Request $request
     * @param array $headers
     * @return array
     */
    private function getBizunitInfo(Request $request, array $headers): array
    {
        if ($request->getAttribute('route')->getName() === 'plan/resource') {
            $args = ($request->getAttribute('params'))->getArguments();
            return [
                'plan-id'          => isset($args['id']) ? rawurldecode($args['id']) : '',
                'bizunit-version'  => isset($args['version']) ? rawurldecode($args['version']) : '',
                'revision-id'      => isset($args['revision']) ? rawurldecode($args['revision']) : '',
                'plan-environment' => isset($args['environment']) ? rawurldecode($args['environment']) : '',
                'plan-test-mode'   => isset($args['test-mode']) ? rawurldecode($args['test-mode']) : ''
            ];
        }

        return [
            'plan-id'          => PlanUtil::extractPlanID($headers),
            'bizunit-version'  => PlanUtil::extractBizunitVersion($headers),
            'revision-id'      => PlanUtil::extractRevisionID($headers),
            'plan-environment' => PlanUtil::extractPlanEnvironment($headers),
            'plan-test-mode'   => PlanUtil::extractPlanTestMode($headers)
        ];
    }

    /**
     * @param S3Mgr $storage
     * @param array $headers
     * @param array $bizunitInfo
     * @return null|array
     * @throws Exception
     */
    private function getPlanData(S3Mgr $storage, array $headers, array $bizunitInfo): ?array
    {
        try {
            return PlanUtil::getPlanData($storage, PlanUtil::extractPlanLoadType($headers), $headers, $bizunitInfo);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return null;
        }
    }

    /**
     * @param array $planData
     * @return string|null
     */
    private function getPlanVersion(array $planData): ?string
    {
        return $planData['plan']['plan-version'];
    }

    /**
     * @param array $bizunitInfo
     * @return string
     */
    private function makeNotfoundMessage(array $bizunitInfo): string
    {
        $messages = [];
        foreach ($bizunitInfo as $key => $value) {
            $messages[] = $key.':'.$value;
        }

        return 'not found:'.implode(', ', $messages);
    }
}
