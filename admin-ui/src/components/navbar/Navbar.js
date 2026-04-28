import React from 'react';
import Tab from "./Tab";
import {UiLabelContext} from "../../Context";

const Navbar = () => {
    const labels = React.useContext(UiLabelContext)
    const tabs = [
        {
            label: "Settings",
            path: "/settings",
            check: ["settings"],
        },
    ]

    return <div className={`wlmi-flex wlmi-h-13 wlmi-w-24 wlmi-gap-1`}>
        {
            tabs.map((tab) => {
                return <Tab tab={tab} key={tab.label}/>
            })
        }
    </div>
};

export default Navbar;