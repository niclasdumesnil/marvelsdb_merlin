<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use AppBundle\Entity\Team;
use AppBundle\Form\TeamType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class TeamController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $teams = array();
        if ($user) {
            $teams = $em->getRepository('AppBundle:Team')->findBy(array('owner' => $user));
        }

        // prepare decoded meta (hero_meta and meta) for each deck so templates can use it
        $deckMetaMap = [];
        foreach ($teams as $team) {
            foreach ($team->getDecks() as $deck) {
                try {
                    $heroMeta = json_decode($deck->getCharacter()->getMeta());
                } catch (\Exception $e) {
                    $heroMeta = null;
                }
                try {
                    $meta = json_decode($deck->getMeta());
                } catch (\Exception $e) {
                    $meta = null;
                }
                $deckMetaMap[$deck->getId()] = ['hero_meta' => $heroMeta, 'meta' => $meta];
            }
        }

        // faction/aspect color mapping (fallback when hero_meta colors are missing)
        $factionColors = [
            'leadership' => '#2b80c5',
            'aggression' => '#cc3038',
            'protection' => '#107116',
            'basic' => '#808080',
            'justice' => '#c0c000',
            'pool' => '#d074ac',
            'determination' => '#493f64',
            'hero' => '#AB006A'
        ];

        // build a gradient CSS string per team using decks' primary colors (hero_meta.colors[0])
        $teamGradientMap = [];
        foreach ($teams as $team) {
            $colors = [];
            foreach ($team->getDecks() as $deck) {
                $dm = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
                $color = null;
                // prefer hero meta primary color
                if ($dm && isset($dm['hero_meta']->colors) && is_array($dm['hero_meta']->colors) && isset($dm['hero_meta']->colors[0])) {
                    $color = $dm['hero_meta']->colors[0];
                }
                // fallback to meta.aspect
                if (!$color && $dm && isset($dm['meta']->aspect)) {
                    $aspect = $dm['meta']->aspect;
                    if (is_string($aspect) && isset($factionColors[$aspect])) {
                        $color = $factionColors[$aspect];
                    }
                }
                // final fallback to deck character faction code
                if (!$color) {
                    try {
                        $faction = $deck->getCharacter()->getFaction();
                        if ($faction && method_exists($faction, 'getCode')) {
                            $code = $faction->getCode();
                            if ($code && isset($factionColors[$code])) {
                                $color = $factionColors[$code];
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                if ($color) {
                    // preserve order but keep unique
                    if (!in_array($color, $colors)) {
                        $colors[] = $color;
                    }
                }
            }

            // helper to pick readable text color (#000 or #fff) from a hex color
            $pickTextColorFromHex = function ($hex) {
                if (!$hex) return '#ffffff';
                $h = ltrim($hex, '#');
                if (strlen($h) == 3) {
                    $r = hexdec(str_repeat($h[0], 2));
                    $g = hexdec(str_repeat($h[1], 2));
                    $b = hexdec(str_repeat($h[2], 2));
                } else {
                    $r = hexdec(substr($h, 0, 2));
                    $g = hexdec(substr($h, 2, 2));
                    $b = hexdec(substr($h, 4, 2));
                }
                $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
                $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
                $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
                $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
                $l = 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
                return ($l > 0.179) ? '#000000' : '#ffffff';
            };

            // we'll try to prefer a hero-provided text color, but only if contrast is sufficient
            $textColor = null;

            // build CSS gradient: if 0 colors => null, if 1 => solid color, else linear-gradient spread
            if (count($colors) === 0) {
                $bg = null;
            } elseif (count($colors) === 1) {
                $bg = $colors[0];
            } else {
                $n = count($colors);
                $stops = [];
                for ($i = 0; $i < $n; $i++) {
                    $pos = intval(round(($i / ($n - 1)) * 100));
                    $stops[] = $colors[$i] . ' ' . $pos . '%';
                }
                $bg = 'linear-gradient(90deg, ' . implode(', ', $stops) . ')';
            }

            // try hero-provided text color but only keep it if contrast vs primary bg is acceptable
            $heroCandidate = null;
            foreach ($team->getDecks() as $deck) {
                $dm2 = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
                if ($dm2 && isset($dm2['hero_meta']->colors) && is_array($dm2['hero_meta']->colors) && isset($dm2['hero_meta']->colors[3]) && $dm2['hero_meta']->colors[3]) {
                    $heroCandidate = $dm2['hero_meta']->colors[3];
                    break;
                }
            }

            // helper: convert hex to rgb array
            $hexToRgb = function ($hex) {
                $h = ltrim($hex, '#');
                if (strlen($h) == 3) {
                    $r = hexdec(str_repeat($h[0], 2));
                    $g = hexdec(str_repeat($h[1], 2));
                    $b = hexdec(str_repeat($h[2], 2));
                } else {
                    $r = hexdec(substr($h, 0, 2));
                    $g = hexdec(substr($h, 2, 2));
                    $b = hexdec(substr($h, 4, 2));
                }
                return [$r, $g, $b];
            };

            // helper: relative luminance
            $relLum = function ($hex) use ($hexToRgb) {
                list($r, $g, $b) = $hexToRgb($hex);
                $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
                $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
                $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
                $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
                return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
            };

            // helper: contrast ratio
            $contrastRatio = function ($hex1, $hex2) use ($relLum) {
                $l1 = $relLum($hex1);
                $l2 = $relLum($hex2);
                if ($l1 < $l2) { $tmp = $l1; $l1 = $l2; $l2 = $tmp; }
                return ($l1 + 0.05) / ($l2 + 0.05);
            };

            // determine sample color to compare with (first color of gradient, or single color)
            if ($bg === null) {
                $sample = '#000000';
            } elseif (strpos($bg, 'linear-gradient') === 0) {
                if (preg_match('/#([0-9a-fA-F]{3,6})/', $bg, $m)) {
                    $sample = '#' . $m[1];
                } else {
                    $sample = '#000000';
                }
            } else {
                $sample = $bg;
            }

            // WCAG threshold: require at least 4.5:1 contrast for normal text
            if ($heroCandidate) {
                try {
                    $ratio = $contrastRatio($heroCandidate, $sample);
                    if ($ratio >= 4.5) {
                        $textColor = $heroCandidate;
                    }
                } catch (\Exception $e) {
                    // ignore and fall back
                }
            }

            // if no explicit acceptable hero text color, pick a readable one from the primary bg
            if (!$textColor) {
                if ($bg === null) {
                    $textColor = '#ffffff';
                } else {
                    $textColor = $pickTextColorFromHex($sample);
                }
            }

            $teamGradientMap[$team->getId()] = ['bg' => $bg, 'text' => $textColor, 'sample' => $sample];
        }

    return $this->render('AppBundle:Team:team.index.html.twig', array('teams' => $teams, 'deck_meta_map' => $deckMetaMap, 'team_gradient_map' => $teamGradientMap));
    }

    public function newAction(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

    $team = new Team();
    // set owner early so validation (NotNull on owner) passes during form validation
    $team->setOwner($user);
    // default visibility to private
    $team->setVisibility('private');
    $em = $this->getDoctrine()->getManager();
    // limit decks choices to the current user's decks (do not pre-filter by visibility here)
    // keep all user's decks available so the client-side visibility filter can show/hide published vs private decks dynamically
    $userDecks = $em->getRepository('AppBundle:Deck')->findBy(['user' => $user]);

    $form = $this->createForm('AppBundle\\Form\\TeamType', $team);
        // ensure visibility default is set on the form as well (in case form rendering overrides)
        if ($form->has('visibility')) {
            try {
                $form->get('visibility')->setData($team->getVisibility());
            } catch (\Exception $e) {
                // ignore
            }
        }
        // override the decks field so only user's decks are selectable
        $form->add('decks', EntityType::class, array(
            'class' => 'AppBundle:Deck',
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => false,
            'required' => true,
            'choices' => $userDecks,
            'choice_attr' => function($choice, $key, $value) {
                try {
                    $isPublished = ($choice->getParent() !== null);
                } catch (\Exception $e) {
                    $isPublished = false;
                }
                return ['data-published' => $isPublished ? '1' : '0'];
            },
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ensure slug exists
            if (!$team->getSlug()) {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($team->getName())));
                $slug = trim($slug, '-');
                $team->setSlug($slug ?: 'team-' . time());
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($team);
            $em->flush();

            return $this->redirect($this->generateUrl('team_view', array('team_id' => $team->getId(), 'slug' => $team->getSlug())));
        }

        // prepare deck choices data (meta + hero_meta) for template visual rendering
        $deckChoices = [];
        foreach ($userDecks as $d) {
            try {
                $heroMeta = json_decode($d->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($d->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckChoices[] = ['decklist' => $d, 'meta' => $meta, 'hero_meta' => $heroMeta];
        }

    return $this->render('AppBundle:Team:team.form.html.twig', array('form' => $form->createView(), 'deck_choices' => $deckChoices));
    }

    public function viewAction($team_id, $slug = null)
    {
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($team_id);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // prepare decoded meta (hero_meta and meta) for each deck so template can use it
        $deckMetaMap = [];
        foreach ($team->getDecks() as $deck) {
            try {
                $heroMeta = json_decode($deck->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($deck->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckMetaMap[$deck->getId()] = ['hero_meta' => $heroMeta, 'meta' => $meta];
        }

        // faction/aspect color mapping (fallback when hero_meta colors are missing)
        $factionColors = [
            'leadership' => '#2b80c5',
            'aggression' => '#cc3038',
            'protection' => '#107116',
            'basic' => '#808080',
            'justice' => '#c0c000',
            'pool' => '#d074ac',
            'determination' => '#493f64',
            'hero' => '#AB006A'
        ];

        // build a gradient CSS string for this team using decks' primary colors (hero_meta.colors[0])
        $colors = [];
        foreach ($team->getDecks() as $deck) {
            $dm = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
            $color = null;
            // prefer hero meta primary color
            if ($dm && isset($dm['hero_meta']->colors) && is_array($dm['hero_meta']->colors) && isset($dm['hero_meta']->colors[0])) {
                $color = $dm['hero_meta']->colors[0];
            }
            // fallback to meta.aspect
            if (!$color && $dm && isset($dm['meta']->aspect)) {
                $aspect = $dm['meta']->aspect;
                if (is_string($aspect) && isset($factionColors[$aspect])) {
                    $color = $factionColors[$aspect];
                }
            }
            // final fallback to deck character faction code
            if (!$color) {
                try {
                    $faction = $deck->getCharacter()->getFaction();
                    if ($faction && method_exists($faction, 'getCode')) {
                        $code = $faction->getCode();
                        if ($code && isset($factionColors[$code])) {
                            $color = $factionColors[$code];
                        }
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }

            if ($color) {
                if (!in_array($color, $colors)) {
                    $colors[] = $color;
                }
            }
        }

        // helper to pick readable text color (#000 or #fff) from a hex color
        $pickTextColorFromHex = function ($hex) {
            if (!$hex) return '#ffffff';
            $h = ltrim($hex, '#');
            if (strlen($h) == 3) {
                $r = hexdec(str_repeat($h[0], 2));
                $g = hexdec(str_repeat($h[1], 2));
                $b = hexdec(str_repeat($h[2], 2));
            } else {
                $r = hexdec(substr($h, 0, 2));
                $g = hexdec(substr($h, 2, 2));
                $b = hexdec(substr($h, 4, 2));
            }
            $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
            $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
            $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
            $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
            $l = 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
            return ($l > 0.179) ? '#000000' : '#ffffff';
        };

        $textColor = null;

        if (count($colors) === 0) {
            $bg = null;
        } elseif (count($colors) === 1) {
            $bg = $colors[0];
        } else {
            $n = count($colors);
            $stops = [];
            for ($i = 0; $i < $n; $i++) {
                $pos = intval(round(($i / ($n - 1)) * 100));
                $stops[] = $colors[$i] . ' ' . $pos . '%';
            }
            $bg = 'linear-gradient(90deg, ' . implode(', ', $stops) . ')';
        }

        // try hero-provided text color but only keep it if contrast vs primary bg is acceptable
        $heroCandidate = null;
        foreach ($team->getDecks() as $deck) {
            $dm2 = isset($deckMetaMap[$deck->getId()]) ? $deckMetaMap[$deck->getId()] : null;
            if ($dm2 && isset($dm2['hero_meta']->colors) && is_array($dm2['hero_meta']->colors) && isset($dm2['hero_meta']->colors[3]) && $dm2['hero_meta']->colors[3]) {
                $heroCandidate = $dm2['hero_meta']->colors[3];
                break;
            }
        }

        $hexToRgb = function ($hex) {
            $h = ltrim($hex, '#');
            if (strlen($h) == 3) {
                $r = hexdec(str_repeat($h[0], 2));
                $g = hexdec(str_repeat($h[1], 2));
                $b = hexdec(str_repeat($h[2], 2));
            } else {
                $r = hexdec(substr($h, 0, 2));
                $g = hexdec(substr($h, 2, 2));
                $b = hexdec(substr($h, 4, 2));
            }
            return [$r, $g, $b];
        };

        $relLum = function ($hex) use ($hexToRgb) {
            list($r, $g, $b) = $hexToRgb($hex);
            $rs = $r / 255; $gs = $g / 255; $bs = $b / 255;
            $rs = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
            $gs = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
            $bs = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);
            return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
        };

        $contrastRatio = function ($hex1, $hex2) use ($relLum) {
            $l1 = $relLum($hex1);
            $l2 = $relLum($hex2);
            if ($l1 < $l2) { $tmp = $l1; $l1 = $l2; $l2 = $tmp; }
            return ($l1 + 0.05) / ($l2 + 0.05);
        };

        if ($bg === null) {
            $sample = '#000000';
        } elseif (strpos($bg, 'linear-gradient') === 0) {
            if (preg_match('/#([0-9a-fA-F]{3,6})/', $bg, $m)) {
                $sample = '#' . $m[1];
            } else {
                $sample = '#000000';
            }
        } else {
            $sample = $bg;
        }

        if ($heroCandidate) {
            try {
                $ratio = $contrastRatio($heroCandidate, $sample);
                if ($ratio >= 4.5) {
                    $textColor = $heroCandidate;
                }
            } catch (\Exception $e) {
                // ignore and fall back
            }
        }

        if (!$textColor) {
            if ($bg === null) {
                $textColor = '#ffffff';
            } else {
                $textColor = $pickTextColorFromHex($sample);
            }
        }

        $teamGradientMap = [];
    $teamGradientMap[$team->getId()] = ['bg' => $bg, 'text' => $textColor, 'sample' => $sample];

        // compute card presence counts across all decks in the team (skip hero / character cards)
        $cardCounts = [];
        // also build per-deck groups of slots by card type (skip hero/character cards)
        $deckSlotGroups = [];
    // map deckId -> cardCode -> faction_code for templating when faction relation is not accessible
    $deckSlotFactionMap = [];
    // map deckId -> cardCode -> type_code for templating when type relation is not accessible
    $deckSlotTypeMap = [];
        foreach ($team->getDecks() as $deck) {
            foreach ($deck->getSlots() as $slot) {
                try {
                    $card = $slot->getCard();
                } catch (\Exception $e) {
                    continue;
                }
                if (!$card) continue;
                $code = $card->getCode();
                // skip the deck's hero/character card
                try {
                    $char = $deck->getCharacter();
                    if ($char && $code == $char->getCode()) continue;
                } catch (\Exception $e) {
                    // ignore
                }
                // skip cards whose type is 'hero' or 'character' if such types exist
                try {
                    $type = $card->getType();
                    $typeCode = $type ? $type->getCode() : null;
                    if ($typeCode === 'hero' || $typeCode === 'character') continue;
                } catch (\Exception $e) {
                    // ignore
                }

                // skip cards whose faction is 'hero' (explicit request)
                try {
                    $cardFaction = $card->getFaction();
                    $cardFactionCode = $cardFaction ? (method_exists($cardFaction, 'getCode') ? $cardFaction->getCode() : null) : null;
                    if ($cardFactionCode === 'hero') continue;
                } catch (\Exception $e) {
                    // ignore
                }

                // count presence per deck
                if (!isset($cardCounts[$code])) $cardCounts[$code] = 0;
                $cardCounts[$code] += 1;

                // group slot by type for this deck
                try {
                    $type = $card->getType();
                } catch (\Exception $e) {
                    $type = null;
                }
                $typeName = null;
                if ($type) {
                    if (method_exists($type, 'getName') && $type->getName()) {
                        $typeName = $type->getName();
                    } elseif (method_exists($type, 'getCode') && $type->getCode()) {
                        $typeName = $type->getCode();
                    }
                    // ensure the Type object exposes a 'code' property for Twig access (slot.card.type.code)
                    try {
                        if (method_exists($type, 'getCode')) {
                            $tcode = $type->getCode();
                            // attach as public property so Twig can read slot.card.type.code even if proxy blocks method access
                            $type->code = $tcode;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
                if (!$typeName) $typeName = 'Other';

                if (!isset($deckSlotGroups[$deck->getId()])) $deckSlotGroups[$deck->getId()] = [];
                if (!isset($deckSlotGroups[$deck->getId()][$typeName])) $deckSlotGroups[$deck->getId()][$typeName] = [];
                // keep Slot entity objects so templates/macros can access slot.card.faction.code
                $deckSlotGroups[$deck->getId()][$typeName][] = $slot;
                // store available faction code by deck and card code for templates as a fallback
                if (!isset($deckSlotFactionMap[$deck->getId()])) $deckSlotFactionMap[$deck->getId()] = [];
                if ($cardFactionCode) {
                    $deckSlotFactionMap[$deck->getId()][$code] = $cardFactionCode;
                }
                // store card type code as a fallback for templates
                try {
                    $cardType = $card->getType();
                    $cardTypeCode = $cardType ? (method_exists($cardType, 'getCode') ? $cardType->getCode() : null) : null;
                } catch (\Exception $e) {
                    $cardTypeCode = null;
                }
                if (!isset($deckSlotTypeMap[$deck->getId()])) $deckSlotTypeMap[$deck->getId()] = [];
                if ($cardTypeCode) {
                    $deckSlotTypeMap[$deck->getId()][$code] = $cardTypeCode;
                }
            }
        }

        // sort groups by type name alphabetically for each deck
        if (!empty($deckSlotGroups)) {
            foreach ($deckSlotGroups as $did => $groups) {
                if (is_array($groups)) {
                    ksort($deckSlotGroups[$did], SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
        }

        $sharedCardCodes = [];
        foreach ($cardCounts as $code => $count) {
            if ($count > 1) $sharedCardCodes[] = $code;
        }

    return $this->render('AppBundle:Team:team.show.html.twig', array(
            'team' => $team,
            'deck_meta_map' => $deckMetaMap,
            'team_gradient_map' => $teamGradientMap,
            'shared_card_codes' => $sharedCardCodes,
            'deck_slot_groups_map' => $deckSlotGroups,
            'deck_slot_faction_map' => $deckSlotFactionMap,
            'deck_slot_type_map' => $deckSlotTypeMap,
        ));
    }

    public function editAction(Request $request, $team_id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($team_id);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }
        if (!$this->isGranted('EDIT', $team)) {
            throw $this->createAccessDeniedException();
        }

        // limit decks choices to the current user's decks; do not pre-filter by visibility so client-side filter can act
        $userDecks = $em->getRepository('AppBundle:Deck')->findBy(['user' => $user]);

        $form = $this->createForm('AppBundle\\Form\\TeamType', $team);
            // ensure visibility field is populated from the team entity when editing
            if ($form->has('visibility')) {
                try {
                    $form->get('visibility')->setData($team->getVisibility());
                } catch (\Exception $e) {
                    // ignore
                }
            }
        // ensure decks selection is limited to user's decks
        $form->add('decks', EntityType::class, array(
            'class' => 'AppBundle:Deck',
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => false,
            'required' => true,
            'choices' => $userDecks,
            'choice_attr' => function($choice, $key, $value) {
                try {
                    $isPublished = ($choice->getParent() !== null);
                } catch (\Exception $e) {
                    $isPublished = false;
                }
                return ['data-published' => $isPublished ? '1' : '0'];
            },
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $decks = $team->getDecks();
            $count = count($decks);
            // slug generation if needed
            if (!$team->getSlug()) {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($team->getName())));
                $slug = trim($slug, '-');
                $team->setSlug($slug ?: 'team-' . time());
            }

            $em->persist($team);
            $em->flush();
            return $this->redirect($this->generateUrl('team_view', array('team_id' => $team->getId(), 'slug' => $team->getSlug())));
        }

        // prepare deck choices data (meta + hero_meta) for template visual rendering
        $deckChoices = [];
        foreach ($userDecks as $d) {
            try {
                $heroMeta = json_decode($d->getCharacter()->getMeta());
            } catch (\Exception $e) {
                $heroMeta = null;
            }
            try {
                $meta = json_decode($d->getMeta());
            } catch (\Exception $e) {
                $meta = null;
            }
            $deckChoices[] = ['decklist' => $d, 'meta' => $meta, 'hero_meta' => $heroMeta];
        }

    return $this->render('AppBundle:Team:team.form.html.twig', array('form' => $form->createView(), 'team' => $team, 'deck_choices' => $deckChoices));
    }

    public function deleteAction(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $teamId = $request->request->get('team_id');
        $em = $this->getDoctrine()->getManager();
        $team = $em->getRepository('AppBundle:Team')->find($teamId);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }
        if (!$this->isGranted('DELETE', $team)) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($team);
        $em->flush();

        return $this->redirect($this->generateUrl('team_index'));
    }
}
