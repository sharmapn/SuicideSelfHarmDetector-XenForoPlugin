import unittest
import xml.etree.ElementTree as ET
from pathlib import Path


ADDON = (
    Path(__file__).resolve().parents[1]
    / "upload"
    / "src"
    / "addons"
    / "Pankaj"
    / "MHFSafeguard"
)


class XenForoMetadataTests(unittest.TestCase):
    def test_option_group_and_every_option_have_display_phrases(self):
        groups = ET.parse(ADDON / "_data" / "option_groups.xml").getroot()
        options = ET.parse(ADDON / "_data" / "options.xml").getroot()
        phrases = ET.parse(ADDON / "_data" / "phrases.xml").getroot()

        phrase_titles = {phrase.attrib["title"] for phrase in phrases.findall("phrase")}

        for group in groups.findall("group"):
            group_id = group.attrib["group_id"]
            self.assertIn(f"option_group.{group_id}", phrase_titles)
            self.assertIn(f"option_group_description.{group_id}", phrase_titles)

        for option in options.findall("option"):
            option_id = option.attrib["option_id"]
            self.assertIn(f"option.{option_id}", phrase_titles)
            self.assertIn(f"option_explain.{option_id}", phrase_titles)


if __name__ == "__main__":
    unittest.main()
