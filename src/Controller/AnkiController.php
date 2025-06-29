<?php

namespace App\Controller;

use App\Entity\AnkiNote;
use App\Form\JsonMergeType;
use App\Repository\AnkiNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

class AnkiController extends AbstractController
{
    #[Route('/anki/test', name: 'app_anki_test')]
    public function test(AnkiNoteRepository $noteRepo): Response
    {
        $noteRepo->findBaseByGuidWithExtra();

        return $this->render('anki/test.html.twig', [
            'controller_name' => 'AnkiController',
        ]);
    }

    #[Route('/anki', name: 'app_anki')]
    public function index(Request $request, EntityManagerInterface $em, AnkiNoteRepository $noteRepo): Response
    {
        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
        }

        $form = $this->createForm(JsonMergeType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $notesKey = $form->get('notes_key')->getData();
            $notesExtraKey = $form->get('notes_extra_key')->getData();
            $notesExtraFields = $form->get('notes_extra_fields')->getData();
            $notesExtraFieldsDestination = $form->get('notes_extra_fields_destination')->getData();

            if (isset($notesKey) && isset($notesExtraKey) && isset($notesExtraFields) && isset($notesExtraFieldsDestination)) {

                $noteRepo->deleteAll();

                $notesJson = json_decode(file_get_contents($form->get('notes_json')->getData()->getPathname()), null, 512, JSON_OBJECT_AS_ARRAY);
                $notesExtraJson = json_decode(file_get_contents($form->get('notes_extra_json')->getData()->getPathname()), null, 512, JSON_OBJECT_AS_ARRAY);

                $notes = $this->getNotesFromFile($notesJson, $notesKey);
                $notesExtra = $this->getNotesFromFile($notesExtraJson, $notesExtraKey, false, [$notesExtraFields]);

                foreach ($notes as $note) {
                    $em->persist($note);
                }

                foreach ($notesExtra as $note) {
                    $em->persist($note);
                }

                $em->flush();

                var_dump(count($notes));
                var_dump(count($notesExtra));

                $notesWithExtra = $noteRepo->findBaseByGuidWithExtra();

                var_dump(count($notesWithExtra));

                foreach ($notesJson['notes'] as $key => $note) {

                    if (!isset($notesWithExtra[$note['guid']])) {
                        continue;
                    }

                    $notesJson['notes'][$key]['fields'][$notesExtraFieldsDestination] = $notesWithExtra[$note['guid']];
                }

                $filePath = tempnam(sys_get_temp_dir(), 'banzeable_');

                file_put_contents($filePath, json_encode($notesJson, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

                dd($filePath);
            }

            /*
            $notesFile = $form->get('notes_json')->getData();
            $notesJson = json_decode(file_get_contents($notesFile->getPathname()), null, 512, JSON_OBJECT_AS_ARRAY);

            $notesModels = [];

            foreach ($notesJson['note_models'] as $noteModelJson) {
                if ($noteModelJson['__type__'] === 'NoteModel') {
                    $modelId = strval($noteModelJson['crowdanki_uuid']);
                    $notesModels[$modelId] = [];
                    foreach ($noteModelJson['flds'] as $field) {
                        $notesModels[$modelId][strval($field['id'])] = $field['name'];
                    }
                }
            }

            // dd($notesModels);
            */
        }

        return $this->render('anki/index.html.twig', [
            'controller_name' => 'AnkiController',
            'form' => $form,
        ]);
    }

    /**
     * @return array<string,AnkiNote>
     */
    private function getNotesFromFile(array $notesJson, int $keyIndex, bool $oneKanjiMode = false, ?array $fieldsIndexes = null)
    {
        $count = 0;
        foreach ($notesJson['note_models'] as $noteModelJson) {
            if ($noteModelJson['__type__'] === 'NoteModel') {
                $count++;
                $modelId = strval($noteModelJson['crowdanki_uuid']);
                $noteFlds = $noteModelJson['flds'];
            }
        }

        if ($count !== 1 || !isset($modelId) || !isset($noteFlds)) {
            throw new \Exception("Exactly one note model expected");
        }

        if (!isset($keyIndex)) {
            throw new \Exception("Notes key not found");
        }

        $notes = [];
        foreach ($notesJson['notes'] as $note) {
            if ($note['__type__'] === 'Note' && $note['note_model_uuid'] === $modelId) {
                $noteObject = new AnkiNote();
                $noteObject->setGuid($note['guid']);

                $ankiKey = $note['fields'][$keyIndex];

                if (isset($fieldsIndexes)) {
                    $fieldsData = [];
                    foreach ($fieldsIndexes as $index) {
                        $fieldsData[] = $note['fields'][$index];
                    }
                } elseif ($oneKanjiMode) {
                    $count = 0;
                    unset($kanjiKey);
                    foreach (mb_str_split($ankiKey) as $char) {
                        if (preg_match('/[\x{4E00}-\x{9FBF}]/u', $char) > 0) {
                            $kanjiKey = $char;
                            $count++;
                        }
                    }

                    if (isset($kanjiKey) && $count === 1) {
                        $ankiKey = $kanjiKey;
                    }
                }

                $noteObject->setAnkiKey($ankiKey);

                if (isset($fieldsData)) {
                    $noteObject->setJson(json_encode($fieldsData, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES));
                }

                $noteObject->setExtra(isset($fieldsIndexes));

                $noteObject->setModelUuid($note['note_model_uuid']);
                $noteObject->setTags($note['tags']);

                $notes[$note['guid']] = $noteObject;
            }
        }

        return $notes;
    }
}
