<?php

namespace Vctls\EntityBundle\Controller;

use AppBundle\Normalizer\EntityNormalizer;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;


/**
 * Entity controller.
 * Enable basic entity management for any given entity.
 *
 * @Route("/entity")
 */
class EntityController extends Controller
{

    /** @var array Array of searchable types and corresponding search syntax. */
    // TODO Define search syntax.
    private $searchableTypes = [
        'integer'   => ["like '%","%'"], // PG
        'string'    => ["like '%","%'"], // PG
        'text'      => ["like '%","%'"], // Oracle
        'date'      => ["like '%","%'"], // PG
        'datetime'  => ["like '%","%'"], // Oracle
    ];

    /**
     * Lists all Entity instances.
     *
     * @Route("/index/{entityName}", name="entity_index", requirements={"entityName": "[\D]+"})
     * @Method("GET")
     * @Template("@VctlsEntity/entity/index.html.twig")
     *
     * @param String $entityName
     * @return Response|array
     */
    public function indexAction($entityName)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);

        /** @var ClassMetadata $metadata */
        $metadata = $em->getClassMetadata($backslashedEntityName);

        // Récupérer un tableau des identifiants de l'entité.
        $id = $metadata->getIdentifier();

        // Récupérer les colonnes.
        $columns = array_merge(
            $metadata->getFieldNames(),
            $metadata->getAssociationNames()
        );

        $searchableTypes = $this->searchableTypes;

        // Make columns searchable.
        $columns = array_map( function($result) use ($metadata, $searchableTypes) {
            $type = $metadata->getTypeOfField($result);


            $data['searchable'] = array_key_exists( $type, $searchableTypes );
            $data['name'] = $result;
            $data['is_entity'] = $metadata->isAssociationWithSingleJoinColumn($result);

            return $data;
        }, $columns);


        return [
            'entityName'    => $entityName,
            'columns'       => $columns,
            'id'            => $id[0],
        ];
    }

    /**
     * Return data for the DataTable with serverside processing.
     *
     * @Route(
     *     "/datatable/{entityName}",
     *     name="datatable",
     *     requirements={"entityName": "[\D]+"}
     * )
     * @Method("GET")
     * @Template("@VctlsEntity/entity/datatable.json.twig")
     *
     * @param Request $request
     * @param String $entityName
     * @return JsonResponse|array
     */
    public function datatableAction(Request $request, $entityName)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);

        // Récupérer les métadonnées.
        $metadata = $em->getClassMetadata($backslashedEntityName);

        // Récupérer un tableau des identifiants de l'entité.
        $id = $metadata->getIdentifier();

        // Compter le nombre total d'enregistrements.
        $qb = $em->createQueryBuilder();
        // TODO Gérer plusieurs identifiants? 
        $qb->select("count(e.$id[0])");
        $qb->from($backslashedEntityName,'e');
        $count = $qb->getQuery()->getSingleScalarResult();

        $qb = $em->createQueryBuilder();
        $qb->select('e')
            ->from($backslashedEntityName, 'e')
            ->setFirstResult((int)$request->get('start'))
            ->setMaxResults((int)$request->get('length'))
        ;

        // Retrieve the columns from the query parameters.
        $columns = $request->get('columns');

        // Retrieve the filter from the query parameters.
        $filter = $request->get('search')['value'];

        // Add filtering clauses.
        foreach ($columns as $column) {
            if ($column['searchable'] == 'true' ) {
                $col = $column['data'];
                $type = $metadata->getTypeOfField($col);
                $before = $this->searchableTypes[$type][0];
                $after = $this->searchableTypes[$type][1];
                // TODO Use private array $searchableTypes.
                $qb->orWhere( "e.$col $before$filter$after" );
            }
        }

        // Add ordering clauses.
        foreach ( $request->get('order') as $order ) {
            $col = $columns[$order['column']]['data'];
            $dir = $order['dir'];
            $qb->addOrderBy("e.$col", "$dir");
        }

        $results = $qb->getQuery()->getResult();

        $normalizer = new EntityNormalizer();

        // Apply necessary convertions and formatting to each result.
        $results = array_map(function($result) use($normalizer) {
            $result = $normalizer->normalize($result);
            return $result;
        }, $results);


        return [
            'entityName'        => $entityName,
            'id'                => $id[0],  // Field to use as Id. Should be rendered as a link.
            'draw'              => (int)$request->get('draw'),
            'recordsTotal'      => $count,
            'recordsFiltered'   => $count,
            'data'              => $results
        ];
    }

    /**
     * Creates a new Entity instance.
     *
     * @Route("/new/{entityName}", name="entity_new", requirements={"entityName": "[\D]+"})
     * @Method({"GET", "POST"})
     * @Template("@VctlsEntity/entity/new.html.twig")
     *
     * @param Request $request
     * @param String $entityName
     * @return RedirectResponse|Response|array
     */
    public function newAction(Request $request, $entityName)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);
        $entityNameWithPartialNamespace = str_replace(":", "\\", $entityName);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);

        // Récupérer les métadonnées.
        $metadata = $em->getClassMetadata($backslashedEntityName);
        $classFqdn = $metadata->getName();

        $reflectionClass = new ReflectionClass($classFqdn);
        $instance = $reflectionClass->newInstance();

        $form = $this->createForm("AppBundle\\Form\\{$entityNameWithPartialNamespace}Type", $instance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($instance);
            $em->flush();

            return $this->redirectToRoute('entity_show', [
                'entityName' => $entityName,
                'id'         => $instance->getId()
            ]);
        }

        return [
            'entityName'    => $entityName,
            'form'          => $form->createView(),
        ];
    }

    /**
     * Finds and displays an Entity instance.
     *
     * @Route(
     *     "/show/{entityName}/{id}",
     *     name="entity_show",
     *     requirements={
     *         "entityName": "[\D]+",
     *         "id": "[\d]+"
     *      }
     * )
     * @Method("GET")
     * @Template("@VctlsEntity/entity/show.html.twig")
     *
     * @param String $entityName
     * @param integer $id
     * @return Response|array
     */
    public function showAction($entityName, $id)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);
        $instance = $em->getRepository($backslashedEntityName)->find($id);

        // Récupérer les colonnes.
        $fields = array_merge(
            $em->getClassMetadata($backslashedEntityName)->getFieldNames(),
            $em->getClassMetadata($backslashedEntityName)->getAssociationNames()
        );

        $deleteForm = $this->createDeleteForm( $entityName, $instance);

        return [
            'entityName'  => $entityName,
            'instance'    => $instance,
            'fields'      => $fields,
            'delete_form' => $deleteForm->createView(),
        ];
    }

    /**
     * Displays a form to edit an existing Entity instance.
     *
     * @Route("/edit/{entityName}/{id}", name="entity_edit", requirements={"entityName": "[\D]+"})
     * @Method({"GET", "POST"})
     * @Template("@VctlsEntity/entity/edit.html.twig")
     *
     * @param Request $request
     * @param String $entityName
     * @param integer $id
     * @return RedirectResponse|Response|array
     */
    public function editAction(Request $request, $entityName, $id)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);
        $instance = $em->getRepository($backslashedEntityName)->find($id);

        // Récupérer les métadonnées.
        /** @var ClassMetadata $metadata */
        $metadata = $em->getClassMetadata($backslashedEntityName);

        // Extraire le nom de toutes les colonnes, associations comprises.
        $fields = array_merge(
            $metadata->getFieldNames(),
            $metadata->getAssociationNames()
        );

        // Remplacer les deux point de l'alias par un antislash pour récupérer le formulaire.
        $noColumnClassName = str_replace(':', '\\', $backslashedEntityName);

        $deleteForm = $this->createDeleteForm( $entityName, $instance);
        $editForm = $this->createForm("AppBundle\\Form\\{$noColumnClassName}Type", $instance);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->persist($instance);
            $em->flush();

            return $this->redirectToRoute('entity_edit', [
                'entityName' => $entityName,
                'id' => $instance->getId()
            ]);
        }

        return [
            'entityName'    => $entityName,
            'instance'      => $instance,
            'fields'        => $fields,
            'edit_form'     => $editForm->createView(),
            'delete_form'   => $deleteForm->createView(),
        ];
    }

    /**
     * Deletes an Entity instance.
     *
     * @Route("/delete/{entityName}/{id}", name="entity_delete", requirements={"entityName": "[\D]+"})
     * @Method("DELETE")
     *
     *
     * @param Request $request
     * @param String $entityName
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, $entityName, $id)
    {
        $backslashedEntityName = str_replace("/", "\\", $entityName);
        $em = $this->getDoctrine()->getManagerForClass($backslashedEntityName);
        $instance = $em->getRepository($backslashedEntityName)->find($id);

        $form = $this->createDeleteForm( $entityName, $instance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($instance);
            $em->flush();
        }

        return $this->redirectToRoute('entity_index', [
            'entityName' => $entityName
        ]);
    }


    /**
     * Creates a form to delete an Entity instance.
     *
     * @param String $entityName The entity name
     * @param Object $instance The Entity instance
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm( $entityName, $instance)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('entity_delete',
                [
                    'entityName' => $entityName,
                    'id'         => $instance->getId()
                ]))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
