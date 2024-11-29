<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Routing\Requirement\Requirement;

use function ksort;

class ProjectController extends AbstractController
{
    #[Route('/', name: 'project_index')]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route('/projet/{id}', name: 'project_show', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function show(ProjectRepository $projectRepository, int $id): Response
    {
        $project = $projectRepository->findOneBy(['id' => $id]);
        if ($project === null) {
            throw new NotFoundHttpException('Ce projet n\'existe pas.');
        }
        $sortedTasks = [];
        $tasks = $project->getTasks();
        foreach ($tasks as $task) {
            $sortedTasks[$task->getStatusId()][] = $task;
        }
        ksort($sortedTasks);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'tasks' => $sortedTasks,
            'statuses' => Status::STATUSES,
        ]);
    }

    #[Route('/projet/creer', name: 'project_create')]
    public function create(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $project = new Project();

        $form = $this->createForm(ProjectType::class, [
            'project' => $project,
            'users' => $project->getUsers()->toArray(),
            'all_users' => $userRepository->findAll(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $project->setName($form->getData()['name']);
            foreach ($form->getData()['users'] as $user) {
                $project->addUser($user);
            }

            $entityManager->persist($project);
            $entityManager->flush();

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }
        return $this->render(
            'project/add-edit.html.twig',
            [
                'form' => $form,
                'project_name' => '',
            ],
        );
    }

    #[Route('/projet/{id}/modifier', name: 'project_edit', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function edit(Request $request, Project $id, EntityManagerInterface $entityManager, ProjectRepository $projectRepository, UserRepository $userRepository): Response
    {
        $project = $projectRepository->findOneBy(['id' => $id]);
        if ($project === null) {
            throw new NotFoundHttpException('Ce projet n\'existe pas.');
        }

        $form = $this->createForm(ProjectType::class, [
            'project' => $project,
            'users' => $project->getUsers()->toArray(),
            'all_users' => $userRepository->findAll(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $project->setName($form->getData()['name']);
            $project->removeAllUsers();
            foreach ($form->getData()['users'] as $user) {
                $project->addUser($user);
            }

            $entityManager->persist($project);
            $entityManager->flush();

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render(
            'project/add-edit.html.twig',
            [
                'form' => $form,
                'project_name' => $project->getName(),
            ],
        );
    }

    #[Route('/projet/{id}/supprimer', name: 'project_delete', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function delete(Project $project, EntityManagerInterface $entityManager): Response
    {
        // suppression de toutes les tâches du projet
        foreach ($project->getTasks() as $task) {
            $entityManager->remove($task);
        }

        //suppression du projet
        $entityManager->remove($project);
        $entityManager->flush();

        return $this->redirectToRoute('project_index');
    }
}
